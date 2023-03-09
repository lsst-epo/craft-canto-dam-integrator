<?php

namespace rosas\dam\services;

use Craft;
use craft\errors\InvalidFieldException;
use craft\services\Elements as ElementsService;
use craft\base\Element;
use rosas\dam\elements\Asset;
use rosas\dam\db\AssetMetadata;
use craft\base\ElementInterface;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;
use craft\helpers\ArrayHelper;
use craft\records\Element as ElementRecord;
use craft\helpers\Db;
use craft\helpers\DateTimeHelper;
use craft\records\Element_SiteSettings as Element_SiteSettingsRecord;
use craft\events\ElementEvent;
use craft\helpers\Queue;
use craft\queue\jobs\UpdateSearchIndex;
use yii\base\Exception;

/**
 *
 */
class Elements extends ElementsService {

    /**
     * @var bool|null Whether we should be updating search indexes for elements if not told explicitly.
     * @since 3.1.2
     */
    private ?bool $_updateSearchIndex;


    // Saving Elements
    // -------------------------------------------------------------------------

    /**
     * Handles all of the routine tasks that go along with saving elements.
     *
     * Those tasks include:
     *
     * - Validating its content (if $validateContent is `true`, or it’s left as `null` and the element is enabled)
     * - Ensuring the element has a title if its type [[Element::hasTitles()|has titles]], and giving it a
     *   default title in the event that $validateContent is set to `false`
     * - Saving a row in the `elements` table
     * - Assigning the element’s ID on the element model, if it’s a new element
     * - Assigning the element’s ID on the element’s content model, if there is one and it’s a new set of content
     * - Updating the search index with new keywords from the element’s content
     * - Setting a unique URI on the element, if it’s supposed to have one.
     * - Saving the element’s row(s) in the `elements_sites` and `content` tables
     * - Deleting any rows in the `elements_sites` and `content` tables that no longer need to be there
     * - Cleaning any template caches that the element was involved in
     *
     * The function will fire `beforeElementSave` and `afterElementSave` events, and will call `beforeSave()`
     *  and `afterSave()` methods on the passed-in element, giving the element opportunities to hook into the
     * save process.
     *
     * Example usage - creating a new entry:
     *
     * ```php
     * $entry = new Entry();
     * $entry->sectionId = 10;
     * $entry->typeId = 1;
     * $entry->authorId = 5;
     * $entry->enabled = true;
     * $entry->title = "Hello World!";
     * $entry->setFieldValues([
     *     'body' => "<p>I can’t believe I literally just called this “Hello World!”.</p>",
     * ]);
     * $success = Craft::$app->elements->saveElement($entry);
     * if (!$success) {
     *     Craft::error('Couldn’t save the entry "'.$entry->title.'"', __METHOD__);
     * }
     * ```
     *
     * @param ElementInterface $element The element that is being saved
     * @param bool $runValidation Whether the element should be validated
     * @param bool $propagate Whether the element should be saved across all of its supported sites
     * (this can only be disabled when updating an existing element)
     * @param bool|null $updateSearchIndex Whether to update the element search index for the element
     * (this will happen via a background job if this is a web request)
     * @param null $assetMetadata
     * @return bool
     * @throws Exception if the $element doesn’t have any supported sites
     * @throws InvalidFieldException
     * @throws \Throwable if reasons
     * @throws \yii\db\Exception
     */
    public function saveElement(ElementInterface $element, bool $runValidation = true, bool $propagate = true, bool $updateSearchIndex = null, $assetMetadata = null): bool
    {
        // Force propagation for new elements
        $propagate = !$element->id || $propagate;

        // Not currently being duplicated
        $duplicateOf = $element->duplicateOf;
        $element->duplicateOf = null;

        $success = $this->_saveElementInternal($element, $runValidation, $propagate, $updateSearchIndex, $assetMetadata);

        $element->duplicateOf = $duplicateOf;
        return $success;
    }

    /**
     * Saves an element.
     *
     * @param ElementInterface $element The element that is being saved
     * @param bool $runValidation Whether the element should be validated
     * @param bool $propagate Whether the element should be saved across all of its supported sites
     * @param bool|null $updateSearchIndex Whether to update the element search index for the element
     * (this will happen via a background job if this is a web request)
     * @param null $assetMetadata
     * @return bool
     * @throws Exception
     * @throws InvalidFieldException
     * @throws \Throwable if reasons
     * @throws \yii\db\Exception
     */
    private function _saveElementInternal(ElementInterface $element, bool $runValidation = true, bool $propagate = true, bool $updateSearchIndex = null, $assetMetadata = null): bool
    {
        /** @var ElementInterface|DraftBehavior|RevisionBehavior $element */
        $isNewElement = !$element->id;

        // Are we tracking changes?
        $trackChanges = ElementHelper::shouldTrackChanges($element);
        $dirtyAttributes = [];

        // Force propagation for new elements
        $propagate = $propagate && $element::isLocalized() && Craft::$app->getIsMultiSite();
        $originalPropagateAll = $element->propagateAll;
        $originalFirstSave = $element->firstSave;

        $element->firstSave = (
            !$element->getIsDraft() &&
            !$element->getIsRevision() &&
            ($element->firstSave || $isNewElement)
        );

        if ($isNewElement) {
            // Give it a UID right away
            if (!$element->uid) {
                $element->uid = StringHelper::UUID();
            }

            if (!$element->getIsDraft() && !$element->getIsRevision()) {
                // Let Matrix fields, etc., know they should be duplicating their values across all sites.
                $element->propagateAll = true;
            }
        }

        // Fire a 'beforeSaveElement' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_ELEMENT)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_ELEMENT, new ElementEvent([
                'element' => $element,
                'isNew' => $isNewElement,
            ]));
        }

        $element->beforeSave($isNewElement);

        if (!$element->beforeSave($isNewElement)) {
            $element->firstSave = $originalFirstSave;
            $element->propagateAll = $originalPropagateAll;
            return false;
        }

        // Get the sites supported by this element
        if (empty($supportedSites = ElementHelper::supportedSitesForElement($element))) {
            $element->firstSave = $originalFirstSave;
            $element->propagateAll = $originalPropagateAll;
            throw new UnsupportedSiteException($element, $element->siteId, 'All elements must have at least one site associated with them.');
        }

        // Make sure the element actually supports the site it's being saved in
        $supportedSiteIds = ArrayHelper::getColumn($supportedSites, 'siteId');
        if (!in_array($element->siteId, $supportedSiteIds, false)) {
            $element->firstSave = $originalFirstSave;
            $element->propagateAll = $originalPropagateAll;
            throw new UnsupportedSiteException($element, $element->siteId, 'Attempting to save an element in an unsupported site.');
        }

        // If the element only supports a single site, ensure it's enabled for that site
        if (count($supportedSites) === 1 && !$element->getEnabledForSite()) {
            $element->enabled = false;
            $element->setEnabledForSite(true);
        }

        // If we're skipping validation, at least make sure the title is valid
        if (!$runValidation && $element::hasContent() && $element::hasTitles()) {
            foreach ($element->getActiveValidators('title') as $validator) {
                $validator->validateAttributes($element, ['title']);
            }
            if ($element->hasErrors('title')) {
                // Set a default title
                if ($isNewElement) {
                    $element->title = Craft::t('app', 'New {type}', ['type' => $element::displayName()]);
                } else {
                    $element->title = $element::displayName() . ' ' . $element->id;
                }
            }
        }

        // Validate
        if ($runValidation && !$element->validate()) {
            Craft::info('Element not saved due to validation error: ' . print_r($element->errors, true), __METHOD__);
            $element->firstSave = $originalFirstSave;
            $element->propagateAll = $originalPropagateAll;
            return false; 
        }

        // Figure out whether we will be updating the search index (and memoize that for nested element saves)
        $oldUpdateSearchIndex = $this->_updateSearchIndex;
        $updateSearchIndex = $this->_updateSearchIndex = $updateSearchIndex ?? $this->_updateSearchIndex ?? true;

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            // No need to save the element record multiple times
            if (!$element->propagating) {
                // Get the element record
                if (!$isNewElement) {
                    $elementRecord = ElementRecord::findOne($element->id);
                    if (!$elementRecord) {
                        $element->firstSave = $originalFirstSave;
                        $element->propagateAll = $originalPropagateAll;
                        throw new ElementNotFoundException("No element exists with the ID '{$element->id}'");
                    }
                } else {
                    $elementRecord = new ElementRecord();
                    $elementRecord->type = get_class($element);
                    $elementRecord->uid = $element->uid;
                }

                // Set the attributes
                $elementRecord->uid = $element->uid;
                $elementRecord->draftId = (int)$element->draftId ?: null;
                $elementRecord->revisionId = (int)$element->revisionId ?: null;
                $elementRecord->fieldLayoutId = $element->fieldLayoutId = (int)($element->fieldLayoutId ?? $element->getFieldLayout()->id ?? 0) ?: null;
                $elementRecord->enabled = (bool)$element->enabled;
                $elementRecord->archived = (bool)$element->archived;

                // todo: remove this check after the next breakpoint
                $schemaVersion = Craft::$app->getInstalledSchemaVersion();
                if (version_compare($schemaVersion, '3.7.0', '>=')) {
                    $elementRecord->canonicalId = $element->getIsDerivative() ? $element->getCanonicalId() : null;
                    $elementRecord->dateLastMerged = Db::prepareDateForDb($element->dateLastMerged);
                }

                if ($isNewElement) {
                    if (isset($element->dateCreated)) {
                        $elementRecord->dateCreated = Db::prepareValueForDb($element->dateCreated);
                    }
                    if (isset($element->dateUpdated)) {
                        $elementRecord->dateUpdated = Db::prepareValueForDb($element->dateUpdated);
                    }
                } else if ($element->resaving) {
                    // Prevent ActiveRecord::prepareForDb() from changing the dateUpdated
                    $elementRecord->markAttributeDirty('dateUpdated');
                } else {
                    // Force a new dateUpdated value
                    $elementRecord->dateUpdated = Db::prepareValueForDb(new \DateTime());
                }

                // Update our list of dirty attributes
                if ($trackChanges) {
                    ArrayHelper::append($dirtyAttributes, ...array_keys($elementRecord->getDirtyAttributes([
                        'fieldLayoutId',
                        'enabled',
                        'archived',
                    ])));
                }

                // Save the element record
                $test = $elementRecord->save(false);

                $dateCreated = DateTimeHelper::toDateTime($elementRecord->dateCreated);

                if ($dateCreated === false) {
                    $element->firstSave = $originalFirstSave;
                    $element->propagateAll = $originalPropagateAll;
                    throw new Exception('There was a problem calculating dateCreated.');
                }

                $dateUpdated = DateTimeHelper::toDateTime($elementRecord->dateUpdated);

                if ($dateUpdated === false) {
                    $element->firstSave = $originalFirstSave;
                    $element->propagateAll = $originalPropagateAll;
                    throw new Exception('There was a problem calculating dateUpdated.');
                }

                // Save the new dateCreated and dateUpdated dates on the model
                $element->dateCreated = $dateCreated;
                $element->dateUpdated = $dateUpdated;

                if ($isNewElement) {
                    // Save the element ID on the element model
                    $element->id = $elementRecord->id;

                    // If there's a temp ID, update the URI
                    if ($element->tempId && $element->uri) {
                        $element->uri = str_replace($element->tempId, $element->id, $element->uri);
                        $element->tempId = null;
                    }
                }
            }

            // Save the element's site settings record
            if (!$isNewElement) {
                $siteSettingsRecord = Element_SiteSettingsRecord::findOne([
                    'elementId' => $element->id,
                    'siteId' => $element->siteId,
                ]);
            }

            if ($element->isNewForSite = empty($siteSettingsRecord)) {
                // First time we've saved the element for this site
                $siteSettingsRecord = new Element_SiteSettingsRecord();
                $siteSettingsRecord->elementId = $element->id;
                $siteSettingsRecord->siteId = $element->siteId;
            }

            $siteSettingsRecord->slug = $element->slug;
            $siteSettingsRecord->uri = $element->uri;

            // Avoid `enabled` getting marked as dirty if it's not really changing
            $enabledForSite = $element->getEnabledForSite();
            if ($siteSettingsRecord->getIsNewRecord() || $siteSettingsRecord->enabled != $enabledForSite) {
                $siteSettingsRecord->enabled = $enabledForSite;
            }

            // Update our list of dirty attributes
            if ($trackChanges && !$element->isNewForSite) {
                ArrayHelper::append($dirtyAttributes, ...array_keys($siteSettingsRecord->getDirtyAttributes([
                    'slug',
                    'uri',
                ])));
                if ($siteSettingsRecord->isAttributeChanged('enabled')) {
                    $dirtyAttributes[] = 'enabledForSite';
                }
            }

            if (!$siteSettingsRecord->save(false)) {
                $element->firstSave = $originalFirstSave;
                $element->propagateAll = $originalPropagateAll;
                throw new Exception('Couldn’t save elements’ site settings record.');
            }

            $element->siteSettingsId = $siteSettingsRecord->id;

            // Save the content
            if ($element::hasContent()) {
                Craft::$app->getContent()->saveContent($element);
            }

            // Set all of the dirty attributes on the element, in case an event listener wants to know
            if ($trackChanges) {
                ArrayHelper::append($dirtyAttributes, ...$element->getDirtyAttributes());
                $element->setDirtyAttributes($dirtyAttributes, false);
            }

            // It is now officially saved
            $assetAfterSaver = new Asset();
            $assetAfterSaver->setAsset($element);

            $assetAfterSaver->afterSave($isNewElement);
//            $propagate = false; // Rosas - Disabling propagation for now
//
//            // Update the element across the other sites?
//            if ($propagate) {
//                $element->newSiteIds = [];
//
//                foreach ($supportedSites as $siteInfo) {
//                    // Skip the initial site
//                    if ($siteInfo['siteId'] != $element->siteId) {
//                        $this->_propagateElement($element, $siteInfo, $isNewElement ? false : null);
//                    }
//                }
//            }

            // It's now fully saved and propagated
            if (
                !$element->propagating &&
                !$element->duplicateOf &&
                !$element->mergingCanonicalChanges
            ) {
                $element->afterPropagate($isNewElement);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $element->firstSave = $originalFirstSave;
            $element->propagateAll = $originalPropagateAll;
            throw $e;
        } finally {
            $this->_updateSearchIndex = $oldUpdateSearchIndex;
        }

        if (!$element->propagating) {
            // Delete the rows that don't need to be there anymore
            if (!$isNewElement) {
                Db::deleteIfExists(
                    Table::ELEMENTS_SITES,
                    [
                        'and',
                        ['elementId' => $element->id],
                        ['not', ['siteId' => $supportedSiteIds]],
                    ]
                );

                if ($element::hasContent()) {
                    Db::deleteIfExists(
                        $element->getContentTable(),
                        [
                            'and',
                            ['elementId' => $element->id],
                            ['not', ['siteId' => $supportedSiteIds]],
                        ]
                    );
                }
            }

            // Invalidate any caches involving this element
            $this->invalidateCachesForElement($element);
        }

        // Update search index
        //$updateSearchIndex = false; // Rosas - disabling the updating of the search index for now
        if ($updateSearchIndex && !ElementHelper::isRevision($element)) {
            $event = new ElementEvent([
                'element' => $element,
            ]);
            $this->trigger(self::EVENT_BEFORE_UPDATE_SEARCH_INDEX, $event);
            if ($event->isValid) {
                if (Craft::$app->getRequest()->getIsConsoleRequest()) {
                    Craft::$app->getSearch()->indexElementAttributes($element);
                } else {
                    Queue::push(new UpdateSearchIndex([
                        'elementType' => get_class($element),
                        'elementId' => $element->id,
                        'siteId' => $propagate ? '*' : $element->siteId,
                        'fieldHandles' => $element->getDirtyFields(),
                    ]), 2048);
                }
            }
        }

        // Update the changed attributes & fields
        if ($trackChanges) {
            $userId = Craft::$app->getUser()->getId();
            $timestamp = Db::prepareDateForDb(new \DateTime());

            foreach ($element->getDirtyAttributes() as $attributeName) {
                Db::upsert(Table::CHANGEDATTRIBUTES, [
                    'elementId' => $element->id,
                    'siteId' => $element->siteId,
                    'attribute' => $attributeName,
                ], [
                    'dateUpdated' => $timestamp,
                    'propagated' => $element->propagating,
                    'userId' => $userId,
                ], [], false);
            }

            if (($fieldLayout = $element->getFieldLayout()) !== null) {
                foreach ($element->getDirtyFields() as $fieldHandle) {
                    if (($field = $fieldLayout->getFieldByHandle($fieldHandle)) !== null) {
                        Db::upsert(Table::CHANGEDFIELDS, [
                            'elementId' => $element->id,
                            'siteId' => $element->siteId,
                            'fieldId' => $field->id,
                        ], [
                            'dateUpdated' => $timestamp,
                            'propagated' => $element->propagating,
                            'userId' => $userId,
                        ], [], false);
                    }
                }
            }
        }

        // Fire an 'afterSaveElement' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_ELEMENT)) {
            $this->trigger(self::EVENT_AFTER_SAVE_ELEMENT, new ElementEvent([
                'element' => $element,
                'isNew' => $isNewElement,
            ]));
        }

        // Clear the element's record of dirty fields
        $element->markAsClean();
        $element->firstSave = $originalFirstSave;
        $element->propagateAll = $originalPropagateAll;

        // Add metadata to the DAM plugin metadata table
        AssetMetadata::upsert($element->id, $assetMetadata);

        return true;
    }

    /**
     * Propagates an element to a different site
     *
     * @param ElementInterface $element
     * @param array $siteInfo
     * @param null $siteElement The element loaded for the propagated site
     * @throws \Throwable
     * @throws InvalidFieldException
     */
    private function _propagateElement(ElementInterface $element, array $siteInfo, $siteElement = null): void
    {
        // Try to fetch the element in this site
        if ($siteElement === null && $element->id) {
            $siteElement = $this->getElementById($element->id, get_class($element), $siteInfo['siteId']);
        } else if (!$siteElement) {
            $siteElement = null;
        }

        // If it doesn't exist yet, just clone the initial site
        if ($isNewSiteForElement = ($siteElement === null)) {
            $siteElement = clone $element;
            $siteElement->siteId = $siteInfo['siteId'];
            $siteElement->siteSettingsId = null;
            $siteElement->contentId = null;
            $siteElement->setEnabledForSite($siteInfo['enabledByDefault']);

            // Keep track of this new site ID
            $element->newSiteIds[] = $siteInfo['siteId'];
        } else if ($element->propagateAll) {
            $oldSiteElement = $siteElement;
            $siteElement = clone $element;
            $siteElement->siteId = $oldSiteElement->siteId;
            $siteElement->contentId = $oldSiteElement->contentId;
            $siteElement->setEnabledForSite($oldSiteElement->enabledForSite);
        } else {
            $siteElement->enabled = $element->enabled;
            $siteElement->resaving = $element->resaving;
        }

        // Does the main site's element specify a status for this site?
        $enabledForSite = $element->getEnabledForSite($siteElement->siteId);
        if ($enabledForSite !== null) {
            $siteElement->setEnabledForSite($enabledForSite);
        }

        // Copy the timestamps
        $siteElement->dateCreated = $element->dateCreated;
        $siteElement->dateUpdated = $element->dateUpdated;

        // Copy the title value?
        if (
            $element::hasTitles() &&
            $siteElement->getTitleTranslationKey() === $element->getTitleTranslationKey()
        ) {
            $siteElement->title = $element->title;
        }

        // Copy the dirty attributes
        $siteElement->setDirtyAttributes($element->getDirtyAttributes());

        // Copy any non-translatable field values
        if ($element::hasContent()) {
            if ($isNewSiteForElement) {
                // Copy all the field values
                $siteElement->setFieldValues($element->getFieldValues());
            } else if (($fieldLayout = $element->getFieldLayout()) !== null) {
                // Only copy the non-translatable field values
                foreach ($fieldLayout->getCustomFields() as $field) {
                    // Has this field changed, and does it produce the same translation key as it did for the initial element?
                    if (
                        $element->isFieldDirty($field->handle) &&
                        $field->getTranslationKey($siteElement) === $field->getTranslationKey($element)
                    ) {
                        // Copy the initial element's value over
                        $siteElement->setFieldValue($field->handle, $element->getFieldValue($field->handle));
                    }
                }
            }
        }

        // Save it
        $siteElement->setScenario(Element::SCENARIO_ESSENTIALS);
        $siteElement->propagating = true;

        $savePropAssets = new Asset();
        $savePropAssets->setAsset($siteElement);
        

        if ($this->_saveElementInternal($savePropAssets, true, false) === false) {
            // Log the errors
            $error = 'Couldn’t propagate element to other site due to validation errors:';
            foreach ($siteElement->getFirstErrors() as $attributeError) {
                $error .= "\n- " . $attributeError;
            }
            Craft::error($error);
            throw new Exception('Couldn’t propagate element to other site.');
        }
    }
}
