console.log("inside of /resources/script.js!!!");

if($("#fields-dam-asset-preview").attr("data-thumbnailurl") == null ||
   $("#fields-dam-asset-preview").attr("data-thumbnailurl") == "none") {
    $("#fields-dam-asset-preview").hide();
} else {
    let url = $("#fields-dam-asset-preview").attr("data-thumbnailurl");
    $("#fields-rosas-clicker").html("Choose a Different DAM Asset");
    $("#fields-dam-asset-preview").prepend(`<img id="fields-dam-preview-image" style="max-height:200px; max-width:200px;" src=${url}/>`);
}

let modalMarkup = $(`
                <div id="rosas-modal" class="modal"> <!-- modal body -->
                    <div id="modal-test" class="body" style="padding: 24px 25px 90px 24px;"> <!-- modal-content -->
                        <header class="header">
                            <h2>Canto Assets</h2>
                        </header>
                        <iframe id="cantoUCFrame" class="canto-uc-subiframe" src=""></iframe>
                        <div id="modal-status-bar">Uploading Image...</div>
                    </div>
                </div>
                `);
$modal = new Garnish.Modal(modalMarkup, { 'autoShow' : false });

let pluginName = "cantoUC";
let cantoUC,
    callback;

$("#fields-remove-dam-asset").click(function(e) {
    let fieldId = e.target.dataset.field;
    let elementId = e.target.dataset.element;
    let assetId = e.target.dataset.asset;
    $.ajax({type:"POST",
        url: "/universal-dam-integrator/dam-asset-removal", 
        dataType:"json", 
        data:{ 
            "elementId": elementId,
	        "fieldId": fieldId	
        }, 
        success:function(data){
            let res = JSON.parse(data);
            if(res.status == "success") {
                $("#fields-rosas-clicker").html("Add a DAM Asset");
                $("#fields-dam-asset-preview").hide();
            } else {
                console.log("logging data!!!");
                console.log(data);
                alert("An error occurred while attempting to remove the image, please try again later.");
            }
        },
        error: function(request) {
            console.log("logging request!!!");
            console.log(request);
            alert("An error occurred while attempting to remove the image, please try again later.");
        }
    });
}); 
    
$("#fields-rosas-clicker").click(function(e) {
    $modal.show();
    let fieldId = e.target.dataset.field;
    let elementId = e.target.dataset.element;
    let type = e.target.dataset.type;
    let accessToken = e.target.dataset.access;
    loadIframeContent(fieldId, elementId, type, accessToken);
});

function dynamicLoadCss(url) {
    let head = document.getElementsByTagName('head')[0];
    let link = document.createElement('link');
    link.type='text/css';
    link.rel = 'stylesheet';
    link.href = url;
    head.appendChild(link);
}

    /*--------------------------load file---------------------------------------*/
function dynamicLoadJs(url, callback) {
    var head = document.getElementsByTagName('head')[0];
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = url;
    if(typeof(callback)=='function'){
        script.onload = script.onreadystatechange = function () {
            if (!this.readyState || this.readyState === "loaded" || this.readyState === "complete"){
                callback();
                script.onload = script.onreadystatechange = null;
            }
        };
    }
    head.appendChild(script);
}


cantoUC = $.fn[pluginName] = $[pluginName] = function (options, callback) {
    /*! options.env:   flightbycanto.com/staging.cantoflight.com/canto.com/canto.global
    */
    settings(options);
    callback = callback;
    loadCantoUCResource();
    // createIframe();
    addEventListener();
    initCantoTag();

    window.onmessage = function(event){
        let data = event.data;
        if(data && data.type == "getTokenInfo"){
            let receiver = document.getElementById('cantoUCFrame').contentWindow;
            tokenInfo.formatDistrict = formatDistrict;
            receiver.postMessage(tokenInfo, '*');
        } else if(data && data.type == "cantoLogout"){
            //clear token and close the frame.
            tokenInfo = {};
            $(".canto-uc-iframe-close-btn").trigger("click");

        } else if(data && data.type == "cantoInsertImage"){
            $(".canto-uc-iframe-close-btn").trigger("click");
            // insertImageToCantoTag(cantoURL);
            callback(currentCantoTagID, data.assetList);

        } else if(data && data.type == "closeModal"){
            $modal.hide();
        } else if(data){
            verifyCode = data;
            // var cantoContentPage = "https://s3-us-west-2.amazonaws.com/static.dmc/universal/cantoContent.html";
            getTokenByVerifycode(verifyCode);
            
        }

    };
};

function getTokenByVerifycode(verifyCode) {
    $.ajax({type:"POST",
        url: "https://oauth.canto.com/oauth/api/oauth2/universal2/token", 
        dataType:"json", 
        data:{ 
            "app_id": appId,
            "grant_type": "authorization_code",
            "redirect_uri": "http://localhost:8080",
            "code": verifyCode,
            "code_verifier": timeStamp
        }, 
        success:function(data){
            tokenInfo = data;
            getTenant(tokenInfo);
            
        },
        error: function(request) {
            alert("Get token error");
        }
    });
}
function getTenant(tokenInfo) {
    $.ajax({type:"GET",
        url: "https://oauth.canto.com:443/oauth/api/oauth2/tenant/" + tokenInfo.refreshToken, 
        success:function(data){
            tokenInfo.tenant = data;
            var cantoContentPage = "./cantoAssets/cantoContent.html";
            $("#cantoUCFrame").attr("src", cantoContentPage);
        },
        error: function(request) {
            alert("Get tenant error");
        }
    });
}

function settings(options){
    var envObj = {
        "flightbycanto.com":"f5ecd6095ebb469691b7398e4945eb44",
        "staging.cantoflight.com":"f18c8f3b79644b168cad5609ff802085",
        "canto.com":"a9dc81b1bf9d492f8ee3838302d266b2",
        "canto.global":"f87b44d366464dfdb4867ab361683c96",
        "canto.de":"e7135823e3d046468287e835008da493",
        "cantodemo.com":"de5c606732a34b44b99ec20c40f6cb5e"
    };
    env = options.env;
    appId = envObj[env];
    formatDistrict = options.extensions;
}

function addEventListener() {

    $(document).on('click',".canto-uc-iframe-close-btn", function(e){
        $("#cantoUCPanel").addClass("hidden");
        $("#cantoUCFrame").attr("src", "");
    })
    .on('click', ".canto-pickup-img-btn", function(e){
        currentCantoTagID = $(e.target).closest("canto").attr("id");
        $("#cantoUCPanel").removeClass("hidden");
        loadIframeContent();
    });
}

    /*--------------------------load iframe content---------------------------------------*/
    function initCantoTag(){
        var body = $("body");
        var cantoTag = body.find("canto");
        var imageHtml = '<button class="canto-pickup-img-btn">+ Insert Files from Canto</button>';

        cantoTag.append(imageHtml);
    }

/*--------------------------load iframe content---------------------------------------*/
function loadIframeContent(fieldId, elementId, type, accessToken) {
    timeStamp = new Date().getTime();
    let tokenInfo = {accessToken: accessToken};
    let cantoLoginPage = "https://oauth.canto.com/oauth/api/oauth2/universal2/authorize?response_type=code&app_id=" + "52ff8ed9d6874d48a3bef9621bc1af26" + "&redirect_uri=http://localhost:8080&state=abcd" + "&code_challenge=" + "1649285048042" + "&code_challenge_method=plain";

    console.log("Inside of script.js about to load UC!");
    var cantoContentPage = "/admin/universal-dam-integrator/cantoContent.html";
    if(tokenInfo.accessToken){
        // $("#cantoUCFrame").attr("data-test", val);
        $("#cantoUCFrame").attr("data-element", elementId);
        $("#cantoUCFrame").attr("data-field", fieldId);
        $("#cantoUCFrame").attr("data-type", type);
        $("#cantoUCFrame").attr("data-access", tokenInfo.accessToken);
        $("#cantoUCFrame").attr("src", cantoContentPage);
    } else {
        $("#cantoUCFrame").attr("data-element", elementId);
        $("#cantoUCFrame").attr("data-field", fieldId);
        $("#cantoUCFrame").attr("data-type", type);
        $("#cantoUCFrame").attr("src", cantoLoginPage);
    }
}

