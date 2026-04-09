(function () {
  var dynamicLoading = {
      css: function (path) {
          var head = document.getElementsByTagName('head')[0];
          var link = document.createElement('link');
          link.href = path;
          link.rel = 'stylesheet';
          link.type = 'text/css';
          head.appendChild(link);
      },
      js: function (path, callback, onFailed) {
          var head = document.getElementsByTagName('head')[0];
          var script = document.createElement('script');
          script.src = path;
          script.type = 'text/javascript';
          script.async = false
          script.onload = script.onreadystatechange = function () {
              callback && callback()
          };
          if (onFailed && typeof (onFailed) == "function") {
              script.onerror = onFailed;
          }
          head.appendChild(script);
      },
      type: 't2024081402',
      time: '1723600210'
  }

dynamicLoading.js('javascript/deString.js');
dynamicLoading.js('javascript/jquery-3.5.1.min.js')
dynamicLoading.css('style/style.css')
dynamicLoading.js('javascript/book.min.js')

// var time = new Date().getTime()
var pageEditorJs = 'files/editor/files/config.js'
var editorTextSvgConfigJs = 'files/editor/files/textSvgConfig.js'
var indexEditorAppCss = 'style/app.css';
var indexEditorChunkVendorsCss = 'style/chunk-vendors.css';
var indexEditorAppJs = 'javascript/app.js';
var indexEditorChunkVendorsJs = 'javascript/chunk-vendors.js';

var loadPageEditorJs = true;
// if(typeof htmlConfig != 'undefined' && typeof htmlConfig['fileExist'] != 'undefined' && typeof htmlConfig['fileExist']['pageEditor'] != 'undefined') {
//   loadPageEditorJs = htmlConfig['fileExist']['pageEditor'] == 1 ? true : false;
// }
if(loadPageEditorJs) {
  dynamicLoading.js(pageEditorJs, function () {
      window.readerConfigLoaded = true;
      if (window.readerConfig &&
          window.readerConfig.pages.length > 0
      ) {
          dynamicLoading.css(indexEditorAppCss)
          dynamicLoading.css(indexEditorChunkVendorsCss)
          dynamicLoading.js(editorTextSvgConfigJs)
          dynamicLoading.js(indexEditorAppJs)
          dynamicLoading.js(indexEditorChunkVendorsJs)
      }
  }, function () {
      window.readerConfigLoaded = true;
  })
} else {
  window.readerConfigLoaded = true;
}

if(window.htmlConfig && window.htmlConfig.pageEditor) window.pageEditor = window.htmlConfig.pageEditor;
window.pageEditorUrl = 'javascript/pageItems.min.js';
if (window.pageEditor &&
  ((!pageEditor.pageAnnos && pageEditor.length > 0) ||
      (pageEditor.pageAnnos && pageEditor.pageAnnos.length > 0))) {
  dynamicLoading.js(pageEditorUrl)
}
window.pageEditorUrl = null;

// window.pageSliderUrl = 'javascript/LoadingJS.js';
// if (window.sliderJS &&
//   window.sliderJS.length > 0) {
//   dynamicLoading.js(pageSliderUrl)
// }
// window.pageSliderUrl = null;

dynamicLoading.js('javascript/main.min.js')

  // try {
  //     if(window.htmlConfig && window.htmlConfig.phoneTemplate) {
  //         if(window.htmlConfig.phoneTemplate.name && window.htmlConfig.phoneTemplate.name == 'neat') {
  //             var toolbarUrl = window.htmlConfig.phoneTemplate.path + 'PhoneToolBar.min.js?' + new Date().getTime();
  //             dynamicLoading.js(toolbarUrl)
  //         }
  //     }
  // } catch (error) {
      
  // }
})();
