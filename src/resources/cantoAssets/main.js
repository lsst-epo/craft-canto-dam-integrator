var cantoViewDom = {};
var _accessToken = "";
var _refreshToken = "";
var _tokenType = "";
var _tenants = "randy.flightbycanto.com";
var cantoAPI = {};
var _APIHeaders = {};
var self = {};
var searchedBy = ""; //bySearch bytree byScheme''
var currentImageList = [];
var singleCountLoad = 50;
var apiNextStart = 0;
var isLoadingComplete = false;
var _formatDistrict = '';
/* -----------------canto API start-------------------------------------------------------------*/

function setToken(tokenInfo){

    _accessToken = tokenInfo.accessToken;
    _tenants = tokenInfo.tenant;
    _tokenType = tokenInfo.tokenType;
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
    // var defer = $.Deferred();
    var url = "https://" + _tenants + "/api/v1/tree/" + treeID;
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
            // defer.resolve(data);
        }
    });
};
cantoAPI.getListByAlbum = function(albumID, callback) {
    if(isLoadingComplete){
        return;
    }
    var filterString = loadMoreHandler();
    var url = "https://" + _tenants + "/api/v1/album/" + albumID + "?" + filterString;
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        // data: data,
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
    var url = previewURL + 'URI';
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        error: function(request) {},
        success: function(data) {
            $("img#" + ID).attr('src',data);
            // $("img#" + ID).closest('.single-image').data("xurl", data)
        }
    });
};
cantoAPI.getHugeRedirectURL = function(previewURL, ID) {
    if(!(previewURL && ID)) return;
    var url = previewURL + 'URI/2000';
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        error: function(request) {},
        success: function(data) {
            var $viewImageModal = self.find("#imageBox");
            $viewImageModal.find("img").attr("src", data);
            // $("imgbox#" + ID).attr('src',data);
            // $("img#" + ID).closest('.single-image').data("xurl", data)
        }
    });
};


cantoAPI.getListByScheme = function(scheme, callback) {
    if(scheme == "allfile") {
        var data = {scheme: "allfile", keywords: ""};
        cantoAPI.getFilterList(data, callback);
    } else {
        if(isLoadingComplete){
            return;
        }
        var filterString = loadMoreHandler();
        var url = "https://" + _tenants + "/api/v1/" + scheme + "?" + filterString;
        $.ajax({
            type: "GET",
            headers:_APIHeaders,
            url: url,
            // data: data,
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
    var url = "https://" + _tenants + "/api/v1/" + scheme + "/" + contentID;
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        // data: data,
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
    var filterString = loadMoreHandler();
    var url = "https://" + _tenants + "/api/v1/search" + "?" + filterString;
    url += "&keyword=" + data.keywords;
    if(data.scheme && data.scheme == "allfile"){
        url += "&scheme=" + encodeURIComponent("image|presentation|document|audio|video|other");
    } else if(data.scheme){
        url += "&scheme=" + data.scheme;
    }
    $.ajax({
        type: "GET",
        headers:_APIHeaders,
        url: url,
        // data: data,
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
    var targetWindow = parent;
    var data = {};
    data.type = "cantoLogout";
    targetWindow.postMessage(data, '*');
};

cantoAPI.insertImage = function(imageArray){
    //clear cookie and trun to login page.
    if(!(imageArray && imageArray.length)){
        return;
    }
    var data = {};
    data.type = "cantoInsertImage";
    data.assetList = [];
    // for(var i = 0; i < imageArray.length; i++){
    //     var downloadUrl = imageArray[i].url + "?Authorization=" + _accessToken;
    //     var previewURI= imageArray[i].url +"/directuri";
    //     var originalUrl = "";
    //      $.ajax({
    //         type: "GET",
    //         headers:_APIHeaders,
    //         url: previewURI,
    //         // data: data,
    //         async: false,
    //         error: function(request) {
    //                 alert("get original Url error");
    //         },
    //         success: function(originalUrl) {
    //             var assetObj = {};
    //             assetObj.downloadurl = downloadUrl;
    //             assetObj.originalUrl = originalUrl;
    //             assetObj.fileName = imageArray[i].fileName;
    //             data.assetList.push(assetObj);
    //         }
    //     });
    // }

    var url = "https://" + _tenants + "/api_binary/v1/batch/directuri";
    $.ajax({
        type: "POST",
        headers: {"Authorization": _tokenType + " " + _accessToken},
        dataType: "json",
        contentType: "application/json; charset=utf-8",
        url: url,
        data: JSON.stringify(imageArray),
        async: true,
        error: function(request) {
                alert("get original Url error");
        },
        success: function(resp) {
        for(var i=0; i<resp.length;i++)
        {
        	for(var j=0;j<imageArray.length;j++)
        		{
        		if(resp[i].id==imageArray[j].id)

        			resp[i].size=imageArray[j].size;
        		}

        }
            // var assetObj = {};
            // assetObj.downloadurl = downloadUrl;
            // assetObj.originalUrl = originalUrl;
            // assetObj.fileName = imageArray[i].fileName;
            data.assetList = resp ;

            var targetWindow = parent;
            targetWindow.postMessage(data, '*');
        }

    });


};

/* -----------------canto API end--------------------------------------------------------*/

$(document).ready(function(){
    self = $("#cantoViewBody");
    getFrameDom();
    addEventListener();
    getTokenInfo();


    window.addEventListener("message", (event) => {
        var data = event.data;
        tokenInfo = data;
        if(tokenInfo && tokenInfo.accessToken && tokenInfo.accessToken.length >0)
        {
        setToken(tokenInfo);
        treeviewDataHandler();
        //init -- get image list
        var initSchme = self.find(".type-font.current").data("type");
        self.find("#globalSearch input").val("");
        getImageInit(initSchme);
        }
    });
});

function getTokenInfo(){
    var targetWindow = parent;
    var data = {};
    data.type = "getTokenInfo";
    targetWindow.postMessage(data, '*');
}

function getFrameDom() {
    var parentDocument = document;
    var contentIframe = document.getElementsByClassName('canto-uc-subiframe')[0];
    if (contentIframe) {
        parentDocument = contentIframe.contentDocument;
    }
    cantoViewDom = parentDocument;
}
function addEventListener() {
    document.addEventListener('sendTokenInfo', function (e) {
        var tokenInfo = e.data;
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
        var type = $(this).data("type");
        self.find("#globalSearch input").val("");
        self.find("#treeviewSection ul li").removeClass("selected");

        var data = {};
        data.scheme = self.find(".type-font.current").data("type");
        data.keywords = "";
        self.find("#imagesContent").html("");
        self.find("#imagesContent").scrollTop(0);
        isLoadingComplete = false;
        currentImageList = [];
        cantoAPI.getFilterList(data, imageListDisplay);

    })
    .on("click","#selectAllBtn",function(e){
        // var isAllSelectedMode = $(this).hasClass("all-selected");
        // if(isAllSelectedMode){
            self.find('.single-image .select-box').removeClass("icon-s-Ok2_32");
            self.find(".single-image").removeClass("selected");
        // } else {
        //     self.find('.single-image .select-box').addClass("icon-s-Ok2_32");
        //     self.find(".single-image").addClass("selected");
        // }
        handleSelectedMode();
    })
    .on("click","#insertAssetsBtn",function(e){
        self.find(".loading-icon").removeClass("hidden");
        var assetArray = [];
        var selectedArray = self.find(".single-image .icon-s-Ok2_32").closest(".single-image");
        for(var i = 0; i < selectedArray.length; i++){
            var obj = {};
            // obj.url = $(selectedArray[i]).data("xurl");
            // obj.fileName = $(selectedArray[i]).data("name");
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
        self.find(".loading-icon").removeClass("hidden");
        var targetURL = $(e.currentTarget).closest(".single-image").data("xurl");
        // cantoAPI.getOriginalResourceUrl(targetURL, displayFullyImage);
        var previewURL = targetURL + "?Authorization=" + _accessToken;
        displayFullyImage(previewURL);
    })
    .on("click",".single-image",function(e){
        self.find(".loading-icon").removeClass("hidden");
        //display image
        var targetURL = $(e.currentTarget).closest(".single-image").data("xurl");
        var targetID = $(e.currentTarget).closest(".single-image").data("id");
        // var previewURL = targetURL + "?Authorization=" + _accessToken;
        // var $viewImageModal = self.find("#imageBox");
        // $viewImageModal.find("img").attr("src", previewURL);
        cantoAPI.getHugeRedirectURL(targetURL, targetID);
        //display detail
        var id = $(this).data("id");
        var scheme = $(this).data("scheme");
        // cantoAPI.getDetail(id, scheme, imageDetail);
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
        var childList = $(e.currentTarget).children("ul");
        // childList.toggleClass("hidden");
        if("treeviewContent" == $(e.currentTarget)[0].id){
            //load init image list.
            self.find("#globalSearch input").val("");
            self.find("#treeviewSection ul li").removeClass("selected");
            self.find(".type-font").removeClass("current");
            self.find("#imagesContent").html("");
            self.find("#imagesContent").scrollTop(0);
            currentImageList = [];
            searchedBy = "";
            isLoadingComplete = false;
            getImageInit("allfile");

        } else if(childList && childList.length){
            childList.animate({
                height:'toggle'
            });
        } else if($(e.currentTarget).hasClass("no-child")){
            // alert("it's a empty folder.");
        } else if($(e.currentTarget).hasClass("has-sub-folder")){
            subTreeId = $(e.currentTarget).data("id");
            $(e.currentTarget).addClass("current-tree-node");
            $(e.currentTarget).find(".folder-loading").removeClass("hidden");
            $(e.currentTarget).find(".icon-s-Folder_open-20px").addClass("hidden");
            cantoAPI.loadSubTree(subTreeId, subTreeRender);

        }else{
            $("#treeviewSection ul li").removeClass("selected");
            self.find(".type-font").removeClass("current");
            $(e.currentTarget).addClass("selected");
            self.find("#globalSearch input").val("");
            self.find("#imagesContent").html("");
            self.find("#imagesContent").scrollTop(0);
            currentImageList = [];
            isLoadingComplete = false;
            searchedBy = "bytree";
            var albumId = $(e.currentTarget).data("id");
            cantoAPI.getListByAlbum(albumId, imageListDisplay);
        }

    })
    .on("click","#globalSearchBtn",function(e){
        var value = self.find("#globalSearch input").val();
        if(!value){
            //load init image list.
            self.find("#treeviewSection ul li").removeClass("selected");
            var initSchme = self.find(".type-font.current").data("type");
            self.find("#globalSearch input").val("");
            self.find("#imagesContent").html("");
            self.find("#imagesContent").scrollTop(0);
            currentImageList = [];
            searchedBy = "";
            isLoadingComplete = false;
            getImageInit(initSchme);
        }
        searchedBy = "bySearch";
        isLoadingComplete = false;
        self.find("#treeviewSection ul li").removeClass("selected");
        self.find(".type-font").removeClass("current");
        var initSchme = self.find(".type-font.current").data("type");
        var data = {};
        data.scheme = initSchme;
        data.keywords = value;
        self.find("#imagesContent").html("");
        self.find("#imagesContent").scrollTop(0);
        currentImageList = [];
        cantoAPI.getFilterList(data, imageListDisplay);
    });
    self.find("#cantoImageBody").on("scroll", function() {
        if(isScrollToPageBottom() && !isLoadingComplete){
            loadMoreAction();
        }
    });

    var inputObj = self.find("#globalSearch input");
    $(inputObj).bind('keyup', function(event) {
        if (event.keyCode == "13") {
            self.find('#globalSearchBtn').click();
        }
    });

    var imageListSection = self.find("#cantoImageBody");
    $(imageListSection).resize(function() {
      imageResize();
    });
}

function getImageInit(scheme){
    cantoAPI.getListByScheme(scheme, imageListDisplay);
}
function imageListDisplay(imageList) {

    // var scheme = self.find("#filterSection").find(".current").data("type");
    if(!(imageList && imageList.length > 0)){
        return;
    }
    // var max = imageList.length > 32 ? 32 : imageList.length;
    var formatArr = [];
    if(_formatDistrict && _formatDistrict.length>1){
        formatArr = _formatDistrict.split(";");
    }
    for(var i = 0; i < imageList.length; i++){
        var d = imageList[i];
        // if(d.scheme == scheme || scheme == "allfile"){
            var extension = d.name.substring(d.name.lastIndexOf('.') + 1);
            if(formatArr.length && !formatArr.includes(extension)){
                continue;
            }
            var html = "";
            // var url = d.url.preview + "/240?Authorization=" + _accessToken;
            var disname = d.name;
            if(d.name.length>150){
                disname = d.name.substr(0,142) + '...' + d.name.substr(-5);
            }
            html += '<div class="single-image" data-id="' + d.id + '" data-scheme="' + d.scheme + '" data-xurl="' + d.url.preview + '" data-name="' + d.name + '" data-size="' + d.size + '" >';
            html += '<img id="' + d.id + '" src="https://s3-us-west-2.amazonaws.com/static.dmc/universal/icon/back.png" alt="' + d.scheme + '">';
            html += '<div class="mask-layer"></div>';
            html += '<div class="single-image-name">' + disname + '</div>';
            //icon-s-Ok2_32
            html += '<span class="select-box icon-s-UnselectedCheck_32  "></span><span class="select-icon-background"></span>';
            html += '</div>';
            self.find("#imagesContent").append(html);
            cantoAPI.getRedirectURL(d.url.preview, d.id);
        // }
    }
    var currentCount = self.find('.single-image').length;
    if(currentCount == 0) {
        self.find("#noItem").removeClass("hidden");
    }else{
        self.find("#noItem").addClass("hidden");
    }
    var rem = new Array();
    self.find('.single-image').hover(function(){
        var nameTop = $(this).height() - $(this).find(".single-image-name").height() - 20;
        $(this).find('.single-image-name').stop().animate({ top: nameTop});
    },function(){
        $(this).find('.single-image-name').stop().animate({top: '100%'});
    });
    self.find('.single-image .select-box').off('click').on('click', function(e) {
        e.cancelBubble = true;
        e.stopPropagation();
        e.preventDefault();

        rem.push($(".single-image").index($(this).closest(".single-image")));
        if(e.shiftKey){
            var iMin =  Math.min(rem[rem.length-2],rem[rem.length-1]);
            var iMax =  Math.max(rem[rem.length-2],rem[rem.length-1]);
            for(i=iMin;i<=iMax;i++){
                var selectedCount = self.find(".single-image .icon-s-Ok2_32").length;
                if(selectedCount >= 20){
                    $(".max-select-tips").fadeIn( "normal").delay(2000).fadeOut(1000);
                    return;
                }
                $(".single-image:eq("+i+") .select-box").addClass("icon-s-Ok2_32");
                $(".single-image:eq("+i+")").addClass("selected");
            }
        } else {
            var selectedCount = self.find(".single-image .icon-s-Ok2_32").length;
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

    var bodyHeight = $("#cantoImageBody").height();
    var documentHeight = $("#imagesContent").height();
    if(documentHeight < bodyHeight && !isLoadingComplete){
        loadMoreAction();
    }
}
var handleSelectedMode = function(){
    var selectedCount = self.find(".single-image .icon-s-Ok2_32").length;
    self.find("#selected-count").html(selectedCount);
    if(selectedCount){
        self.find("#globalSearch").addClass("hidden");
        self.find("#filterSection").addClass("hidden");
        self.find("#selectedCountSection").removeClass("hidden");
        self.find("#selectedActionSection").removeClass("hidden");
    } else {
        self.find("#globalSearch").removeClass("hidden");
        self.find("#filterSection").removeClass("hidden");
        self.find("#selectedCountSection").addClass("hidden");
        self.find("#selectedActionSection").addClass("hidden");
    }
    //toggle isAllSelectedMode
    var currentAssetsCount = self.find(".single-image").length;
    // if(currentAssetsCount == selectedCount){
        self.find("#selectAllBtn").addClass("all-selected");
        self.find("#selectAllBtn").attr("title", "Deselect All");
    // } else {
    //     self.find("#selectAllBtn").removeClass("all-selected");
    //     self.find("#selectAllBtn").attr("title", "Select All");
    // }
};
var resetImageURL = function(id, url){
    var imgDom = self.find("#" + id);
    var data = "data:image" + url;
    imgDom.attr("src", data);
};

function displayFullyImage(src) {
    var $viewImageModal = self.find("#viewImageModal");
    var $pageMask = self.find("#pageMask");
    $viewImageModal.find("img").attr("src", src);
    self.find(".loading-icon").addClass("hidden");
    $viewImageModal.removeClass("hidden");
    $pageMask.removeClass("hidden");
    self.find('.view-image-modal .close-btn').off('click').on('click', function() {
        $viewImageModal.addClass("hidden");
        $pageMask.addClass("hidden");
    });
}


function imageDetail(detailData) {
    if(detailData){
        self.find("#imageDetailModal_name").html(detailData.name);
        self.find("#imageDetailModal_size").html(detailData.size + "KB");
        self.find("#imageDetailModal_created").html(dateHandler(detailData.created));
        self.find("#imageDetailModal_uploaded").html(dateHandler(detailData.lastUploaded));
        self.find("#imageDetailModal_status").html(detailData.approvalStatus);
        self.find("#insertIntoPostBtn").data("downloadurl", detailData.url.download);


    var $imageDetailModal = self.find("#imageDetailModal");
    self.find(".loading-icon").addClass("hidden");
    $imageDetailModal.removeClass("hidden");
    self.find('#imageDetailModal .close-btn').off('click').on('click', function() {
        $imageDetailModal.addClass("hidden");
    });
    }
}

function imageNewDetail(detailData){
    var sliceString = function(string, dom, length){
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
        self.find("#imagebox_name").html(detailData.name);
        self.find("#imagebox_size").html(Math.round(detailData.size/1024) + "KB");
        self.find("#imagebox_created").html(detailData.metadata ? (detailData.metadata["Create Date"] ? detailData.metadata["Create Date"] : " ") : " ");
        self.find("#imagebox_uploaded").html(dateHandler(detailData.lastUploaded));
        self.find("#imagebox_status").html(detailData.approvalStatus);
        var copyrightMoreDom = $("#imagebox_copyright").closest(".detail-item").find(".more");
        self.find("#imagebox_copyright").html(sliceString(detailData.copyright, copyrightMoreDom, 177));
        self.find("#imagebox_copyright").data("field",detailData.copyright);
        var tactMoreDom = $("#imagebox_tac").closest(".detail-item").find(".more");
        self.find("#imagebox_tac").html(sliceString(detailData.termsAndConditions, tactMoreDom, 160));
        self.find("#imagebox_tac").data("field",detailData.termsAndConditions);
        self.find("#insertBtn").data("id", detailData.id);
        self.find("#insertBtn").data("scheme", detailData.scheme);
    }

    var $imageDetailModal = self.find("#imagePreviewModal");
    self.find(".loading-icon").addClass("hidden");
    $imageDetailModal.removeClass("hidden");
    self.find('#imagePreviewModal .close-btn').off('click').on('click', function() {
        $imageDetailModal.addClass("hidden");
    });
    self.find('#imagePreviewModal #cancelBtn').off('click').on('click', function() {
        $imageDetailModal.addClass("hidden");
    });
    self.find('#imagePreviewModal .detail-item .more').off('click').on('click', function() {
        var text = $(this).closest(".detail-item").find(".content").data("field");
        $(this).closest(".detail-item").find(".content").html(text);
        $(this).addClass("hidden");
    });
    self.find('#imagePreviewModal #insertBtn').off('click').on('click', function() {
        // var downloaderURL = self.find('#imagePreviewModal #insertBtn').data("downloadurl");
        self.find(".loading-icon").removeClass("hidden");
        var assetArray = [];
        var obj = {};
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

var treeviewController= function(dummyData) {
    var self = $(cantoViewDom);
    // console.log(dummyData);
    var html = "";
    html = treeviewFirstRender(dummyData);
    self.find("#treeviewContent").append(html);
    self.find("#treeviewContent > ul").animate({
        height:'toggle'
    });

};
var treeviewFirstRender = function(data){
    var html = "<ul style='display: none;'>";
    $.each(data, function(i, d){
        var listclass = " ";
        if(d.size == 0){
            listclass = "no-child";
        } else if(d.scheme == "folder"){
            listclass = "has-sub-folder";
        }
        html += '<li data-id="' + d.id + '"  class="' + listclass + '">';
        var iconStyle = "icon-s-Folder_open-20px";
        if(d.scheme == "album"){
            iconStyle = "icon-s-Album-20px";
        }
        html += '<i class="' + iconStyle + '"></i>';
        html += '<img src="https://s3-us-west-2.amazonaws.com/static.dmc/universal/icon/cantoloading.gif" class="folder-loading hidden" alt="Loading">';
        html += '<span>' + d.name + '</span>';
        html += '</li>';
    });
    html += "</ul>";
    return html;
};
var subTreeRender  = function(data){
    var html = treeviewRender(data);
    self.find(".current-tree-node").append(html);
    self.find(".current-tree-node > ul").animate({
        height:'toggle'
    });
    self.find(".current-tree-node").find(".folder-loading").addClass("hidden");
    self.find(".current-tree-node").find(".icon-s-Folder_open-20px").removeClass("hidden");
    self.find(".current-tree-node").removeClass("current-tree-node");
};
var treeviewRender = function(data){
    var html = "<ul style='display: none;'>";
    $.each(data, function(i, d){
        var listclass = " ";
        if(d.size == 0){
            listclass = "no-child";
        }
        html += '<li data-id="' + d.id + '"  class="' + listclass + '">';
        var iconStyle = "icon-s-Folder_open-20px";
        if(d.scheme == "album"){
            iconStyle = "icon-s-Album-20px";
        }
        html += '<i class="' + iconStyle + '"></i>';
        html += '<span>' + d.name + '</span>';
        if(d.children && d.children.length){
            html += treeviewRender(d.children);
        }
        html += '</li>';
    });
    html += "</ul>";
    return html;
};

function imageResize(){
    var initCount = 8;
    var totalWidth = totalWidth = Number(self.find("#imagesContent")[0].offsetWidth);
    var singleImageWidth = 0;
    var getCountInALine = function(n){
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
    var singleWidth = getCountInALine(initCount);
    self.find('.single-image').css("width",singleWidth);
};

//scroll to load more

function isScrollToPageBottom(){
    var bodyHeight = $("#cantoImageBody").height();
    var documentHeight = $("#imagesContent").height();
    var scrollHeight = $("#cantoImageBody").scrollTop();
    var isToBottom = documentHeight - bodyHeight - scrollHeight < 0;
    var nowCount = $(".single-image").length == 0;
    return isToBottom && !nowCount;
}

function loadMoreHandler(){
    var start = currentImageList.length == 0 ? 0 : apiNextStart;
    var filterString = "sortBy=time&sortDirection=descending&limit=" + singleCountLoad + "&start=" + start;
    var imageCount = $(".single-image").length;
    if(imageCount !== 0){
        $("#loadingMore").fadeIn( "slow");
    } else {
        self.find("#imagesContent").html("");
    }
    return filterString;
}

function loadMoreAction(){
    if(searchedBy == "bySearch"){
        var value = self.find("#globalSearch input").val();
        if(!value){
            return;
        }
        var initSchme = self.find(".type-font.current").data("type");
        var data = {};
        data.scheme = initSchme;
        data.keywords = value;
        cantoAPI.getFilterList(data, imageListDisplay);
    }else if(searchedBy == "bytree"){
        var albumId = self.find("#treeviewSection ul li").find(".selected").data("id");
        cantoAPI.getListByAlbum(albumId, imageListDisplay);
    }else{
        var initSchme = self.find(".type-font.current").data("type");
        getImageInit(initSchme);
    }
}

