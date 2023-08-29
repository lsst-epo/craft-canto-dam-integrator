let cantoViewDom = {};
let _accessToken = "";
let _refreshToken = "";
let _tokenType = "";
let _tenants = "";
let cantoAPI = {};
let _APIHeaders = {};
let searchedBy = ""; //bySearch bytree byScheme''
let currentImageList = [];
let singleCountLoad = 50;
let apiNextStart = 0;
let isLoadingComplete = false;
let _formatDistrict = '';

/* -----------------canto API start-------------------------------------------------------------*/

function setToken(tokenInfo){
    _accessToken = tokenInfo.accessToken;
    _tenants = tokenInfo.tenant ? tokenInfo.tenant :  "rubin.canto.com/";
    _tokenType = tokenInfo.tokenType ? tokenInfo.tokenType : "bearer";
    _APIHeaders = {
        "Authorization": _tokenType + " " + _accessToken,
        "Content-Type": "application/x-www-form-urlencoded"
    };
    _formatDistrict = tokenInfo.formatDistrict;
}

cantoAPI.loadTree = function(callback) {
    var url = "https://" + _tenants + "/api/v1/tree?sortBy=name&sortDirection=ascending&layer=1";
    $.ajax({
        headers:_APIHeaders,
        type: "GET",
        url: url,
        async: true,
        error: function(request) {
             alert("load tree error");
        },
        success: function(data) {
            callback(data.results);
        }
    });
};
cantoAPI.loadSubTree = function(treeID, callback) {
    let url = `https://${_tenants}/api/v1/tree/${treeID}`;
    $.ajax({
        headers:_APIHeaders,
        type: "GET",
        url: url,
        async: true,
        error: function(request) {
            alert("load tree error");
        },
        success: function(data) {
            callback(data.results);
        }
    });
};
cantoAPI.getListByAlbum = function(albumID, callback) {
    if(isLoadingComplete){
        return;
    }
    let filterString = loadMoreHandler();
    let url = `https://${_tenants}/api/v1/album/${albumID}?${filterString}`;
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        async: true,
        error: function(request) {
             alert("load list error");
        },
        success: function(data) {
            currentImageList.push.apply(currentImageList, data.results);
            if(!data.start) {
                data.start = 0;
            }
            if(data.found - data.limit <= data.start){
                isLoadingComplete = true;
            } else {
                isLoadingComplete = false;
            }
            apiNextStart = data.start + data.limit + 1;
            $("#loadingMore").delay(1500).fadeOut( "slow");
            callback(data.results);
        }
    });
};
cantoAPI.getRedirectURL = function(previewURL, ID) {
    if(!(previewURL && ID)) return;
    let url = previewURL + 'URI';
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        error: function(request) {},
        success: function(data) {
            $("img#" + ID).attr('src',data);
        }
    });
};
cantoAPI.getHugeRedirectURL = function(previewURL, ID) {
    if(!(previewURL && ID)) return;
    let url = `${previewURL}URI/2000`;
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        error: function(request) {},
        success: function(data) {
            let $viewImageModal = $("#cantoViewBody").find("#imageBox");
            $viewImageModal.find("img").attr("src", data);
        }
    });
};


cantoAPI.getListByScheme = function(scheme, callback) {
    if(scheme == "allfile") {
        let data = {scheme: "allfile", keywords: ""};
        cantoAPI.getFilterList(data, callback);
    } else {
        if(isLoadingComplete){
            return;
        }
        let filterString = loadMoreHandler();
        let url = `https://${_tenants}/api/v1/${scheme}?${filterString}`;
        $.ajax({
            type: "GET",
            headers:_APIHeaders,
            url: url,
            async: false,
            error: function(request) {
                 alert("load list error");
            },
            success: function(data) {
                currentImageList.push.apply(currentImageList, data.results);
                if(!data.start) {
                    data.start = 0;
                }
                if(data.found - data.limit <= data.start){
                    isLoadingComplete = true;
                } else {
                    isLoadingComplete = false;
                }
                apiNextStart = data.start + data.limit + 1;
                $("#loadingMore").delay(1500).fadeOut( "slow");
                callback(data.results);
            }
        });
    }

};

cantoAPI.getDetail = function(contentID, scheme, callback) {
    let url = `https://${_tenants}/api/v1/${scheme}/${contentID}`;
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        async: true,
        error: function(request) {
             alert("load detail error");
        },
        success: function(data) {
            callback(data);
        }
    });
};

cantoAPI.getFilterList = function(data, callback) {
    if(isLoadingComplete){
        return;
    }
    let filterString = loadMoreHandler();
    let url = `https://${_tenants}/api/v1/search?${filterString}`;
    url += `&keyword=${data.keywords}`;
    if(data.scheme && data.scheme == "allfile"){
        url += `&scheme=${encodeURIComponent("image|presentation|document|audio|video|other")}`;
    } else if(data.scheme){
        url += `&scheme=${data.scheme}`;
    }
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        async: false,
        error: function(request) {
             alert("load List error");
        },
        success: function(data) {
            currentImageList.push.apply(currentImageList, data.results);
            if(!data.start) {
                data.start = 0;
            }
            if(data.found - data.limit <= data.start){
                isLoadingComplete = true;
            } else {
                isLoadingComplete = false;
            }
            apiNextStart = data.start + data.limit + 1;
            $("#loadingMore").delay(1500).fadeOut( "slow");
            callback(data.results);
        }
    });
};

cantoAPI.logout = function(){
    //clear cookie and trun to login page.
    let targetWindow = parent;
    let data = {};
    data.type = "cantoLogout";
    targetWindow.postMessage(data, '*');
};

cantoAPI.insertImage = function(imageArray){
    //clear cookie and trun to login page.
    if(!(imageArray && imageArray.length)){
        return;
    }
    let data = {};
    data.type = "cantoInsertImage";
    data.assetList = [];

    let url = `https://${_tenants}/api_binary/v1/batch/directuri`;

    fetch(url, {
        method: "post",
        headers:  {
            "Authorization": `${_tokenType} ${_accessToken}`,
            "Content-Type": "application/json; charset=utf-8"
        },
        body: JSON.stringify(imageArray)
    }).then(response => {
        return response.json();
    }).then(resp => {
        for(let i=0; i<resp.length;i++) {
            for(let j=0;j<imageArray.length;j++) {
                if(resp[i].id==imageArray[j].id) {
                    resp[i].size=imageArray[j].size;
                }
            }
        }
        fetch("/universal-dam-integrator/dam-asset-upload", {
            method: "post",
            headers: { "Content-Type" : "application/json; charset=utf-8" },
            body: JSON.stringify({
                cantoId: resp[0].id,
                fieldId: window.frameElement.getAttribute("data-field"),
                elementId: window.frameElement.getAttribute("data-element"),
                entryType: window.frameElement.getAttribute("data-type")
            }),
        }).then(response => {
            return response.json();
        }).then(resp => {
            let targetWindow = parent;
            let data = {};
            data.type = "closeModal";
            data.thumbnailUrl = JSON.parse(resp)["asset_thumbnail"];
            targetWindow.postMessage(data, '*');
        });
        data.assetList = resp ;
        let targetWindow = parent;
        targetWindow.postMessage(data, '*');
    });
};

/* -----------------canto API end--------------------------------------------------------*/

$(document).ready(function(){
    getFrameDom();
    addEventListener();
    getTokenInfo();

    window.addEventListener("message", (event) => {
        let tokenInfo = event.data;

        if(tokenInfo && tokenInfo.accessToken && tokenInfo.accessToken.length >0) {
            setToken(tokenInfo);
        } else {
            setToken({
                accessToken: parent.document.querySelector("#cantoUCFrame").dataset.access
            });
        }
        treeviewDataHandler();
        let initSchme = $("#cantoViewBody").find(".type-font.current").data("type");
        $("#cantoViewBody").find("#globalSearch input").val("");
        getImageInit(initSchme);
    });
});

function getTokenInfo(){
    let targetWindow = parent;
    let data = {};
    data.type = "getTokenInfo";
    targetWindow.postMessage(data, '*');
}

function getFrameDom() {
    let parentDocument = document;
    let contentIframe = document.getElementsByClassName('canto-uc-subiframe')[0];
    if (contentIframe) {
        parentDocument = contentIframe.contentDocument;
    }
    cantoViewDom = parentDocument;
}
function addEventListener() {
    document.addEventListener('sendTokenInfo', function (e) {
        let tokenInfo = e.data;
        _accessToken = tokenInfo.accessToken;
        _refreshToken = tokenInfo.refreshToken;
        _tokenType = tokenInfo.tokenType;
    });

    $(document).off('click').on("click","#treeviewSwitch",function(e){
        if($('#treeviewSection').hasClass("expanded")){
            $('#treeviewSection').stop().animate({
                left: '-20%'
            });
            $('#cantoImageBody').stop().animate({
                width: '100%',
                left: '0'
            }, imageResize);
            $('#treeviewSection').removeClass("expanded");
            $("#loadingMore").addClass("no-treeview");
            $("#noItem").addClass("no-treeview");
            $(".max-select-tips").addClass("no-treeview");
        } else {
            $('#treeviewSection').stop().animate({
                left: '0px'
            });
            $('#cantoImageBody').stop().animate({
                width: '80%',
                left: '20%'
            }, imageResize);
            $('#treeviewSection').addClass("expanded");
            $("#loadingMore").removeClass("no-treeview");
            $("#noItem").removeClass("no-treeview");
            $(".max-select-tips").removeClass("no-treeview");
        }

    })
    .on("click",".type-font",function(e){
        searchedBy = "byScheme";
        $(".type-font").removeClass("current");
        $(this).addClass("current");
        // let type = $(this).data("type");
        $("#cantoViewBody").find("#globalSearch input").val("");
        $("#cantoViewBody").find("#treeviewSection ul li").removeClass("selected");

        let data = {};
        data.scheme = $("#cantoViewBody").find(".type-font.current").data("type");
        data.keywords = "";
        $("#cantoViewBody").find("#imagesContent").html("");
        $("#cantoViewBody").find("#imagesContent").scrollTop(0);
        isLoadingComplete = false;
        currentImageList = [];
        cantoAPI.getFilterList(data, imageListDisplay);
        
    })
    .on("click","#selectAllBtn",function(e){
        $("#cantoViewBody").find('.single-image .select-box').removeClass("icon-s-Ok2_32");
        $("#cantoViewBody").find(".single-image").removeClass("selected");
        handleSelectedMode();
    })
    .on("click","#insertAssetsBtn",function(e){
        $("#cantoViewBody").find(".loading-icon").removeClass("hidden");
        let assetArray = [];
        let selectedArray = $("#cantoViewBody").find(".single-image .icon-s-Ok2_32").closest(".single-image");
        for(let i = 0; i < selectedArray.length; i++){
            let obj = {};
            obj.id = $(selectedArray[i]).data("id");
            obj.scheme = $(selectedArray[i]).data("scheme");
            obj.size = $(selectedArray[i]).data("size");
            assetArray.push(obj);
        }
        cantoAPI.insertImage(assetArray);
    })
    .on("click",".icon-s-Fullscreen",function(e){
        e.cancelBubble = true;
        e.stopPropagation();
        e.preventDefault();
        $("#cantoViewBody").find(".loading-icon").removeClass("hidden");
        let targetURL = $(e.currentTarget).closest(".single-image").data("xurl");
        let previewURL = targetURL + "?Authorization=" + _accessToken;
        displayFullyImage(previewURL);
    })
    .on("click",".single-image",function(e){
        $("#cantoViewBody").find(".loading-icon").removeClass("hidden");
        //display image
        let targetURL = $(e.currentTarget).closest(".single-image").data("xurl");
        let targetID = $(e.currentTarget).closest(".single-image").data("id");
        cantoAPI.getHugeRedirectURL(targetURL, targetID);
        //display detail
        let id = $(this).data("id");
        let scheme = $(this).data("scheme");
        cantoAPI.getDetail(id, scheme, imageNewDetail);
    })
    .on("click","#logoutBtn",function(e){
        $(".loading-icon").removeClass("hidden");
        cantoAPI.logout();
    })
    //treeview event
    .on("click","#treeviewSection ul li",function(e){
        e.cancelBubble = true;
        e.stopPropagation();
        e.preventDefault();
        let childList = $(e.currentTarget).children("ul");
        // childList.toggleClass("hidden");
        if("treeviewContent" == $(e.currentTarget)[0].id){
            //load init image list.
            $("#cantoViewBody").find("#globalSearch input").val("");
            $("#cantoViewBody").find("#treeviewSection ul li").removeClass("selected");
            $("#cantoViewBody").find(".type-font").removeClass("current");
            $("#cantoViewBody").find("#imagesContent").html("");
            $("#cantoViewBody").find("#imagesContent").scrollTop(0);
            currentImageList = [];
            searchedBy = "";
            isLoadingComplete = false;
            console.log("line 499");
            getImageInit("allfile");

        } else if(childList && childList.length){
            childList.animate({
                height:'toggle'
            });
        } else if($(e.currentTarget).hasClass("has-sub-folder")){
            subTreeId = $(e.currentTarget).data("id");
            $(e.currentTarget).addClass("current-tree-node");
            $(e.currentTarget).find(".folder-loading").removeClass("hidden");
            $(e.currentTarget).find(".icon-s-Folder_open-20px").addClass("hidden");
            cantoAPI.loadSubTree(subTreeId, subTreeRender);

        } else {
            $("#treeviewSection ul li").removeClass("selected");
            $("#cantoViewBody").find(".type-font").removeClass("current");
            $(e.currentTarget).addClass("selected");
            $("#cantoViewBody").find("#globalSearch input").val("");
            $("#cantoViewBody").find("#imagesContent").html("");
            $("#cantoViewBody").find("#imagesContent").scrollTop(0);
            currentImageList = [];
            isLoadingComplete = false;
            searchedBy = "bytree";
            let albumId = $(e.currentTarget).data("id");
            cantoAPI.getListByAlbum(albumId, imageListDisplay);
        }

    })
    .on("click","#globalSearchBtn",function(e){
        let value = $("#cantoViewBody").find("#globalSearch input").val();
        if(!value){
            //load init image list.
            $("#cantoViewBody").find("#treeviewSection ul li").removeClass("selected");
            let initSchme = $("#cantoViewBody").find(".type-font.current").data("type");
            $("#cantoViewBody").find("#globalSearch input").val("");
            $("#cantoViewBody").find("#imagesContent").html("");
            $("#cantoViewBody").find("#imagesContent").scrollTop(0);
            currentImageList = [];
            searchedBy = "";
            isLoadingComplete = false;
            console.log("line 492");
            getImageInit(initSchme);
        }
        searchedBy = "bySearch";
        isLoadingComplete = false;
        $("#cantoViewBody").find("#treeviewSection ul li").removeClass("selected");
        $("#cantoViewBody").find(".type-font").removeClass("current");
        let initSchme = $("#cantoViewBody").find(".type-font.current").data("type");
        let data = {};
        data.scheme = initSchme;
        data.keywords = value;
        $("#cantoViewBody").find("#imagesContent").html("");
        $("#cantoViewBody").find("#imagesContent").scrollTop(0);
        currentImageList = [];
        cantoAPI.getFilterList(data, imageListDisplay);
    });
    $("#cantoViewBody").find("#cantoImageBody").on("scroll", function() {
        if(isScrollToPageBottom() && !isLoadingComplete){
            loadMoreAction();
        }
    });

    let inputObj = $("#cantoViewBody").find("#globalSearch input");
    $(inputObj).bind('keyup', function(event) {
        if (event.keyCode == "13") {
            $("#cantoViewBody").find('#globalSearchBtn').click();
        }
    });

    let imageListSection = $("#cantoViewBody").find("#cantoImageBody");
    $(imageListSection).resize(function() {
      imageResize();
    });
}

function getImageInit(scheme){
    console.log("inside of getImageInit()!");
    console.log("schema: ", scheme);
    cantoAPI.getListByScheme(scheme, imageListDisplay);
}
function imageListDisplay(imageList) {
    if(!(imageList && imageList.length > 0)){
        return;
    }
    let formatArr = [];
    if(_formatDistrict && _formatDistrict.length>1){
        formatArr = _formatDistrict.split(";");
    }
    for(let i = 0; i < imageList.length; i++){
        let d = imageList[i];
        let extension = d.name.substring(d.name.lastIndexOf('.') + 1);
        if(formatArr.length && !formatArr.includes(extension)){
            continue;
        }
        let html = "";
        let disname = d.name;
        if(d.name.length>150){
            disname = d.name.substr(0,142) + '...' + d.name.substr(-5);
        }
        html += `<div class="single-image" data-id="${d.id}" data-scheme="${d.scheme}" data-xurl="${d.url.preview}" data-name="${d.name}" data-size="${d.size}" >
                    <img id="${d.id}" src="https://s3-us-west-2.amazonaws.com/static.dmc/universal/icon/back.png" alt="${d.scheme}">
                    <div class="mask-layer"></div>
                    <div class="single-image-name">${disname}</div>
                    <span class="select-box icon-s-UnselectedCheck_32  "></span><span class="select-icon-background"></span>
                </div>`;
        $("#cantoViewBody").find("#imagesContent").append(html);
        cantoAPI.getRedirectURL(d.url.preview, d.id);
    }
    let currentCount = $("#cantoViewBody").find('.single-image').length;
    if(currentCount == 0) {
        $("#cantoViewBody").find("#noItem").removeClass("hidden");
    }else{
        $("#cantoViewBody").find("#noItem").addClass("hidden");
    }
    let rem = new Array();
    $("#cantoViewBody").find('.single-image').hover(function(){
        let nameTop = $(this).height() - $(this).find(".single-image-name").height() - 20;
        $(this).find('.single-image-name').stop().animate({ top: nameTop});
    },function(){
        $(this).find('.single-image-name').stop().animate({top: '100%'});
    });
    $("#cantoViewBody").find('.single-image .select-box').off('click').on('click', function(e) {
        e.cancelBubble = true;
        e.stopPropagation();
        e.preventDefault();

        rem.push($(".single-image").index($(this).closest(".single-image")));
        if(e.shiftKey){
            let iMin =  Math.min(rem[rem.length-2],rem[rem.length-1]);
            let iMax =  Math.max(rem[rem.length-2],rem[rem.length-1]);
            for(i=iMin;i<=iMax;i++){
                let selectedCount = $("#cantoViewBody").find(".single-image .icon-s-Ok2_32").length;
                if(selectedCount >= 20){
                    $(".max-select-tips").fadeIn( "normal").delay(2000).fadeOut(1000);
                    return;
                }
                $(".single-image:eq("+i+") .select-box").addClass("icon-s-Ok2_32");
                $(".single-image:eq("+i+")").addClass("selected");
            }
        } else {
            let selectedCount = $("#cantoViewBody").find(".single-image .icon-s-Ok2_32").length;
            if(selectedCount >= 20){
                if(!$(this).hasClass("icon-s-Ok2_32")){
                    $(".max-select-tips").fadeIn( "normal").delay(2000).fadeOut(1000);
                }
                $(this).removeClass("icon-s-Ok2_32");
                $(this).closest(".single-image").removeClass("selected");
                return;
            }else{
                $(this).toggleClass("icon-s-Ok2_32");
                $(this).closest(".single-image").toggleClass("selected");
            }

        }
        handleSelectedMode();
    });
    imageResize();
    handleSelectedMode();

    let bodyHeight = $("#cantoImageBody").height();
    let documentHeight = $("#imagesContent").height();
    if(documentHeight < bodyHeight && !isLoadingComplete){
        loadMoreAction();
    }
}
let handleSelectedMode = function(){
    let selectedCount = $("#cantoViewBody").find(".single-image .icon-s-Ok2_32").length;
    $("#cantoViewBody").find("#selected-count").html(selectedCount);
    if(selectedCount){
        $("#cantoViewBody").find("#globalSearch").addClass("hidden");
        $("#cantoViewBody").find("#filterSection").addClass("hidden");
        $("#cantoViewBody").find("#selectedCountSection").removeClass("hidden");
        $("#cantoViewBody").find("#selectedActionSection").removeClass("hidden");
    } else {
        $("#cantoViewBody").find("#globalSearch").removeClass("hidden");
        $("#cantoViewBody").find("#filterSection").removeClass("hidden");
        $("#cantoViewBody").find("#selectedCountSection").addClass("hidden");
        $("#cantoViewBody").find("#selectedActionSection").addClass("hidden");
    }
    //toggle isAllSelectedMode
    $("#cantoViewBody").find("#selectAllBtn").addClass("all-selected");
    $("#cantoViewBody").find("#selectAllBtn").attr("title", "Deselect All");
};
let resetImageURL = function(id, url){
    let imgDom = $("#cantoViewBody").find("#" + id);
    let data = "data:image" + url;
    imgDom.attr("src", data);
};

function displayFullyImage(src) {
    let $viewImageModal = $("#cantoViewBody").find("#viewImageModal");
    let $pageMask = $("#cantoViewBody").find("#pageMask");
    $viewImageModal.find("img").attr("src", src);
    $("#cantoViewBody").find(".loading-icon").addClass("hidden");
    $viewImageModal.removeClass("hidden");
    $pageMask.removeClass("hidden");
    $("#cantoViewBody").find('.view-image-modal .close-btn').off('click').on('click', function() {
        $viewImageModal.addClass("hidden");
        $pageMask.addClass("hidden");
    });
}


function imageDetail(detailData) {
    if(detailData){
        $("#cantoViewBody").find("#imageDetailModal_name").html(detailData.name);
        $("#cantoViewBody").find("#imageDetailModal_size").html(detailData.size + "KB");
        $("#cantoViewBody").find("#imageDetailModal_created").html(dateHandler(detailData.created));
        $("#cantoViewBody").find("#imageDetailModal_uploaded").html(dateHandler(detailData.lastUploaded));
        $("#cantoViewBody").find("#imageDetailModal_status").html(detailData.approvalStatus);
        $("#cantoViewBody").find("#insertIntoPostBtn").data("downloadurl", detailData.url.download);

        let $imageDetailModal = $("#cantoViewBody").find("#imageDetailModal");
        $("#cantoViewBody").find(".loading-icon").addClass("hidden");
        $imageDetailModal.removeClass("hidden");
        $("#cantoViewBody").find('#imageDetailModal .close-btn').off('click').on('click', function() {
            $imageDetailModal.addClass("hidden");
        });
    }
}

function imageNewDetail(detailData){
    let sliceString = function(string, dom, length){
        if(!string) {
            $(dom).closest(".detail-item").addClass("hidden");
            return "Null";
        } else {
            $(dom).closest(".detail-item").removeClass("hidden");
        }
        if(!length) {
            length = 150;
        }
        if(string.length > length) {
            $(dom).removeClass("hidden");
            return string.slice(0, length) + "...";
        } else {
            $(dom).addClass("hidden");
            return string;
        }
    };
    if(detailData){
        $("#cantoViewBody").find("#imagebox_name").html(detailData.name);
        $("#cantoViewBody").find("#imagebox_size").html(Math.round(detailData.size/1024) + "KB");
        $("#cantoViewBody").find("#imagebox_created").html(detailData.metadata ? (detailData.metadata["Create Date"] ? detailData.metadata["Create Date"] : " ") : " ");
        $("#cantoViewBody").find("#imagebox_uploaded").html(dateHandler(detailData.lastUploaded));
        $("#cantoViewBody").find("#imagebox_status").html(detailData.approvalStatus);
        let copyrightMoreDom = $("#imagebox_copyright").closest(".detail-item").find(".more");
        $("#cantoViewBody").find("#imagebox_copyright").html(sliceString(detailData.copyright, copyrightMoreDom, 177));
        $("#cantoViewBody").find("#imagebox_copyright").data("field",detailData.copyright);
        let tactMoreDom = $("#imagebox_tac").closest(".detail-item").find(".more");
        $("#cantoViewBody").find("#imagebox_tac").html(sliceString(detailData.termsAndConditions, tactMoreDom, 160));
        $("#cantoViewBody").find("#imagebox_tac").data("field",detailData.termsAndConditions);
        $("#cantoViewBody").find("#insertBtn").data("id", detailData.id);
        $("#cantoViewBody").find("#insertBtn").data("scheme", detailData.scheme);
    }

    let $imageDetailModal = $("#cantoViewBody").find("#imagePreviewModal");
    $("#cantoViewBody").find(".loading-icon").addClass("hidden");
    $imageDetailModal.removeClass("hidden");
    $("#cantoViewBody").find('#imagePreviewModal .close-btn').off('click').on('click', function() {
        $imageDetailModal.addClass("hidden");
    });
    $("#cantoViewBody").find('#imagePreviewModal #cancelBtn').off('click').on('click', function() {
        $imageDetailModal.addClass("hidden");
    });
    $("#cantoViewBody").find('#imagePreviewModal .detail-item .more').off('click').on('click', function() {
        let text = $(this).closest(".detail-item").find(".content").data("field");
        $(this).closest(".detail-item").find(".content").html(text);
        $(this).addClass("hidden");
    });
    $("#cantoViewBody").find('#imagePreviewModal #insertBtn').off('click').on('click', function() {
        $("#cantoViewBody").find(".loading-icon").removeClass("hidden");
        let assetArray = [];
        let obj = {};
        obj.id = detailData.id;
        obj.scheme = detailData.scheme;
        obj.size = detailData.size;
        assetArray.push(obj);
        cantoAPI.insertImage(assetArray);
    });
}

function dateHandler(str){
    return str.substr(0, 4) + '-' + str.substr(4, 2) + '-'
        + str.substr(6, 2) + ' ' + str.substr(8, 2) + ':' + str.substr(10, 2);
}

function treeviewDataHandler() {
    cantoAPI.loadTree(treeviewController);
}

let treeviewController= function(dummyData) {
    let html = "";
    html = treeviewFirstRender(dummyData);
    $("#cantoViewBody").find("#treeviewContent").append(html);
    $("#cantoViewBody").find("#treeviewContent > ul").animate({
        height:'toggle'
    });

};
let treeviewFirstRender = function(data){
    let html = "<ul style='display: none;'>";
    $.each(data, function(i, d){
        let listclass = " ";
        if(d.size == 0){
            listclass = "no-child";
        } else if(d.scheme == "folder"){
            listclass = "has-sub-folder";
        }
        html += `<li data-id="${d.id}"  class="${listclass}">`;
        let iconStyle = "icon-s-Folder_open-20px";
        if(d.scheme == "album"){
            iconStyle = "icon-s-Album-20px";
        }
        html += `<i class="${iconStyle}"></i>
                    <img src="https://s3-us-west-2.amazonaws.com/static.dmc/universal/icon/cantoloading.gif" class="folder-loading hidden" alt="Loading">
                    <span>${d.name}</span>
                </li>`;
    });
    html += "</ul>";
    return html;
};
let subTreeRender  = function(data){
    let html = treeviewRender(data);
    $("#cantoViewBody").find(".current-tree-node").append(html);
    $("#cantoViewBody").find(".current-tree-node > ul").animate({
        height:'toggle'
    });
    $("#cantoViewBody").find(".current-tree-node").find(".folder-loading").addClass("hidden");
    $("#cantoViewBody").find(".current-tree-node").find(".icon-s-Folder_open-20px").removeClass("hidden");
    $("#cantoViewBody").find(".current-tree-node").removeClass("current-tree-node");
};
let treeviewRender = function(data){
    let html = "<ul style='display: none;'>";
    $.each(data, function(i, d){
        let listclass = " ";
        if(d.size == 0){
            listclass = "no-child";
        }
        html += `<li data-id="${d.id}"  class="${listclass}">`;
        let iconStyle = "icon-s-Folder_open-20px";
        if(d.scheme == "album"){
            iconStyle = "icon-s-Album-20px";
        }
        html += `<i class="${iconStyle}"></i>
                    <span>${d.name}</span>`;
        if(d.children && d.children.length){
            html += treeviewRender(d.children);
        }
        html += '</li>';
    });
    html += "</ul>";
    return html;
};

function imageResize(){
    let initCount = 8;
    // let totalWidth = totalWidth = Number($("#cantoViewBody").find("#imagesContent")[0].offsetWidth);
    let totalWidth = Number($("#cantoViewBody").find("#imagesContent")[0].offsetWidth);
    let singleImageWidth = 0;
    let getCountInALine = function(n){
        singleImageWidth = Number((totalWidth - 8)/n - 2);
        if((singleImageWidth >= 170) && (singleImageWidth <= 210)){
            return singleImageWidth;
        }else if(singleImageWidth < 170){
            n--;
            getCountInALine(n);
        }else if(singleImageWidth > 210){
            n++;
            getCountInALine(n);
        }
    };
    let singleWidth = getCountInALine(initCount);
    $("#cantoViewBody").find('.single-image').css("width",singleWidth);
};

//scroll to load more

function isScrollToPageBottom(){
    let bodyHeight = $("#cantoImageBody").height();
    let documentHeight = $("#imagesContent").height();
    let scrollHeight = $("#cantoImageBody").scrollTop();
    let isToBottom = documentHeight - bodyHeight - scrollHeight < 0;
    let nowCount = $(".single-image").length == 0;
    return isToBottom && !nowCount;
}

function loadMoreHandler(){
    let start = currentImageList.length == 0 ? 0 : apiNextStart;
    let filterString = "sortBy=time&sortDirection=descending&limit=" + singleCountLoad + "&start=" + start;
    let imageCount = $(".single-image").length;
    if(imageCount !== 0){
        $("#loadingMore").fadeIn( "slow");
    } else {
        $("#cantoViewBody").find("#imagesContent").html("");
    }
    return filterString;
}

function loadMoreAction(){
    if(searchedBy == "bySearch"){
        let value = $("#cantoViewBody").find("#globalSearch input").val();
        if(!value){
            return;
        }
        let initSchme = $("#cantoViewBody").find(".type-font.current").data("type");
        let data = {};
        data.scheme = initSchme;
        data.keywords = value;
        cantoAPI.getFilterList(data, imageListDisplay);
    }else if(searchedBy == "bytree"){
        let albumId = $("#cantoViewBody").find("#treeviewSection ul li").find(".selected").data("id");
        cantoAPI.getListByAlbum(albumId, imageListDisplay);
    }else{
        let initSchme = $("#cantoViewBody").find(".type-font.current").data("type");
        getImageInit(initSchme);
    }
}

function uploadClick(e) {
    document.querySelector("#uploadBtnInvisible").click();
}

function uploadFileToCanto(e) {
    let url = `https://${_tenants}/api/v1/upload/setting`;
    fetch(url, {
        method: "GET",
        headers: {
            "Authorization": `${_tokenType} ${_accessToken}`,
            "Content-Type": "application/json; charset=utf-8"
        },
    }).then(response => {
        return response.json();
    }).then(data => {
        const formData = new FormData();
        formData.append("key", data.key);
        formData.append("acl", data.acl);
        formData.append("AWSAccessKeyId", data.AWSAccessKeyId);
        formData.append("Policy", data.Policy);
        formData.append("Signature", data.Signature);
        formData.append("x-amz-meta-file_name", e.files[0].name);
        formData.append("x-amz-meta-tag", "");
        formData.append("x-amz-meta-scheme", "");
        formData.append("x-amz-meta-id", "");
        formData.append("x-amz-meta-album_id", "");
        formData.append("file", e.files[0]);
        let statusBar = parent.document.querySelector("#modal-status-bar");

        fetch(data.url, {
            method: "post",
            body: formData,
            mode: "no-cors",
            redirect: 'follow'
        }).then(response => {
            statusBar.style.display = "block";
            statusBar.innerHTML = "Uploading image...";

        }).catch(error => {
            console.log(error);
        }).finally(() => {
            statusBar.innerHTML = "Uploading - This can take several minutes...";
            checkStatusInterval(e.files[0].name);
        });
    }).catch(error => {
        console.log("An error occurred while attempting to grab upload settings!");
        console.log(error);
    });

    function checkStatusInterval(filename) {
        let url = `https://${_tenants}/api/v1/upload/status?hours=1`;
        let statusBar = parent.document.querySelector("#modal-status-bar");
        statusChecker = setInterval(() => {
            
            fetch(url, {
                method: "get",
                headers: {"Authorization": _tokenType + " " + _accessToken},
            }).then(response => {
                return response.json();
            }).then(body => {
                if(body.results && body.results.length > 0) {
                    let results = body.results.filter(e => {
                        console.log("evaluating: ", e);
                        if(e.name == filename && e.status != "Done") {
                            console.log("match!!!");
                            return e;
                        }
                    });
                    if(results.filter(e => e != undefined).length == 0) {
                        statusBar.innerHTML = "Canto processing complete! Reloading"
                        window.location.reload();
                    }
                }
            }).catch(error => {
                console.log("an error occurred!");
                console.log(error)
            });
        }, 5000);
    }

}

parent.document.querySelector("#modal-status-bar").style.display = "none";
