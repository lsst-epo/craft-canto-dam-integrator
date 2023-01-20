
$(document).ready(function(){
    // $("#uploadBtn").change(e => {
    //     console.log("uploaded!");
    //     console.log(e);
    // });

    $.cantoUC({
        env: "canto.com",
    }, replaceCantoTagByImage);
});
function calcImageSize(num) {
    var size = Math.round(Number(num)/1024);
    return size < 1024 ? size + "KB" : Math.round(size/1024) + "MB";
}
// $("#uploadBtn").change(e => {
//     console.log("uploaded!");
//     console.log(e);
// });

function replaceCantoTagByImage(id, assetArray){
    var body = $("body");
    var cantoTag = body.find("canto" + "#" + id);
    var imageHtml = "";
    for(var i = 0; i < assetArray.length; i++){
        imageHtml += '<div class="canto-block">';
        imageHtml += '<img class="canto-preview-img" src="' + assetArray[i].previewUri + '">';
        imageHtml += '<div class="canto-preview-name">Name: ' + assetArray[i].displayName + '</div>';
        imageHtml += '<div class="canto-preview-size">Size: ' + calcImageSize(assetArray[i].size) + '</div>';
        imageHtml += '<a class="canto-preview-size" href="' + assetArray[i].directUri + '">Download</a>';
        imageHtml += '</div>';
    }
    cantoTag.replaceWith(imageHtml);
}

// Beginning of Canto's Universal Connector code:
(function ($, document, window) {
    var cantoUC,
    pluginName = "cantoUC",
    tokenInfo = {},
    env = "canto.com", 
    appId = "52ff8ed9d6874d48a3bef9621bc1af26",
    currentCantoTagID,
    formatDistrict,
    timeStamp;

    // $("#uploadBtn").change(e => {
    //     console.log("uploaded!");
    //     console.log(e);
    // });

    cantoUC = $.fn[pluginName] = $[pluginName] = function (options, callback) {
        settings(options);
        callback = callback;

        window.onmessage=function(event){
            var data = event.data;
            if(data && data.type == "getTokenInfo"){
                var receiver = document.getElementById('cantoUCFrame').contentWindow;
                tokenInfo.formatDistrict = formatDistrict;
                receiver.postMessage(tokenInfo, '*');
            } else if(data && data.type == "cantoLogout"){
                tokenInfo = {};
                $(".canto-uc-iframe-close-btn").trigger("click");

            } else if(data && data.type == "cantoInsertImage"){
                $(".canto-uc-iframe-close-btn").trigger("click");
                callback(currentCantoTagID, data.assetList);

            } else if(data && data.type == "closeModal"){
                $("#fields-dam-preview-image").remove(); 
                $("#fields-rosas-clicker").html("Choose a Different DAM Asset");
                $("#fields-dam-asset-preview").prepend(`<img id="fields-dam-preview-image" style="max-height:200px; max-width:200px;" src=${data.thumbnailUrl}/>`);
                $("#fields-dam-asset-preview").show();
                $modal.hide();

            } else if(data){
                verifyCode = data;
                getTokenByVerifycode(verifyCode);
                
            }

        };
    };
    function settings(options){
        env = options.env;
        formatDistrict = options.extensions;
    }

    function getTokenByVerifycode(verifyCode) {
        $.ajax({type:"POST",
            url: "https://oauth.canto.com/oauth/api/oauth2/universal2/token", 
            dataType:"json", 
            data:{ 
                "app_id": appId,
                "grant_type": "authorization_code",
                "redirect_uri": "http://localhost:8080",
                "code": verifyCode,
                "code_verifier": "1649285048042"
            }, 
            success:function(data){
                tokenInfo = data;
                getTenant(tokenInfo);
                
            },
            error: function(request) {
                alert("Get token errorz");
            }
        });
    }
    function getTenant(tokenInfo) {
        $.ajax({type:"GET",
            url: "https://oauth." + env + ":443/oauth/api/oauth2/tenant/" + tokenInfo.refreshToken, 
            success:function(data){
                tokenInfo.tenant = data;
                console.log("in test.js loading UC!");
                $("#cantoUCFrame").attr("src", "/admin/universal-dam-integrator/cantoContent.html");
            },
            error: function(request) {
                alert("Get tenant error");
            }
        });
    }

}(jQuery, document, window));
