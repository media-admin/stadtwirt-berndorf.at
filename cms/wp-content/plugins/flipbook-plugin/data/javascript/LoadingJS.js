// BUILD_DATE: 2025-04-10
var str_css =
  '@keyframes loadingAnimate{from {-webkit-transform: rotateY(0deg) scale(0.8);-o-transform: rotateY(0deg) scale(0.8);-ms-transform: rotateY(0deg) scale(0.8);-moz-transform: rotateY(0deg) scale(0.8);transform: rotateY(0deg) scale(0.8);}to {-webkit-transform: rotateY(-180deg) scale(0.8);-o-transform: rotateY(-180deg) scale(0.8);-ms-transform: rotateY(-180deg) scale(0.8);-moz-transform: rotateY(-180deg) scale(0.8);transform: rotateY(-180deg) scale(0.8);}}' +
  '@-webkit-keyframes loadingAnimate{from {-webkit-transform: rotateY(0deg) scale(0.8);-o-transform: rotateY(0deg) scale(0.8);-ms-transform: rotateY(0deg) scale(0.8);-moz-transform: rotateY(0deg) scale(0.8);transform: rotateY(0deg) scale(0.8);}to {-webkit-transform: rotateY(-180deg) scale(0.8);-o-transform: rotateY(-180deg) scale(0.8);-ms-transform: rotateY(-180deg) scale(0.8);-moz-transform: rotateY(-180deg) scale(0.8);transform: rotateY(-180deg) scale(0.8);}}' +
  '.loadingRun{-webkit-animation : loadingAnimate 1.2s infinite;animation : loadingAnimate 1.2s infinite;}' +
  '.loader,.loader:after {border-radius: 50%;width: 60px;height: 60px;}' +
  '.loader {margin: 60px auto;font-size: 10px;position: relative;text-indent: -9999em;border-top: 1.1em solid rgba(255, 255, 255, 0.2);border-right: 1.1em solid rgba(255, 255, 255, 0.2);border-bottom: 1.1em solid rgba(255, 255, 255, 0.2);' +
  'border-left: 1.1em solid #ffffff;-webkit-transform: translateZ(0);-ms-transform: translateZ(0);transform: translateZ(0);-webkit-animation: load8 1.1s infinite linear;animation: load8 1.1s infinite linear;}' +
  '@-webkit-keyframes load8 {0% {-webkit-transform: rotate(0deg);transform: rotate(0deg);}100% {-webkit-transform: rotate(360deg);transform: rotate(360deg);}}' +
  '@keyframes load8 {0% {-webkit-transform: rotate(0deg);transform: rotate(0deg);}100% {-webkit-transform: rotate(360deg);transform: rotate(360deg);}}';

const boolMap = {
  no: false,
  hide: false,
  false: false,
  0: false,
  yes: true,
  show: true,
  true: true,
  1: true,
  enable: true,
  disable: false
};

function add_css(str_css) {
  try {
    //IE下可行
    var style = document.createStyleSheet();
    style.cssText = str_css;
  } catch (e) {
    //Firefox,Opera,Safari,Chrome下可行
    var style = document.createElement('style');
    style.type = 'text/css';
    style.textContent = str_css;
    document.getElementsByTagName('HEAD').item(0).appendChild(style);
  }
}

add_css(str_css);

var loadingIconBase64 =
  'data:image/svg+xml;base64,PHN2ZyB0PSIxNTI1OTE2MjIyMjk5IiBjbGFzcz0iaWNvbiIgc3R5bGU9IiIgdmlld0JveD0iMTMwIDAgODAwIDEwMjQiIHZlcnNpb249IjEuMSINCiAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiBwLWlkPSIyNDc4IiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayINCiAgd2lkdGg9IjQ5IiBoZWlnaHQ9IjU2Ij4NCiAgPGRlZnM+DQogICAgPHN0eWxlIHR5cGU9InRleHQvY3NzIj48L3N0eWxlPg0KICA8L2RlZnM+DQogIDxwYXRoDQogICAgZD0iTTgzNS41NTAyNyA0OC43NjE5MDVDODc2LjgwNTEyMiA0OC43NjE5MDUgOTEwLjIyMjIyMyA4MS40NDExNTggOTEwLjIyMjIyMyAxMjEuNzUzNjA0TDkxMC4yMjIyMjMgOTAyLjA5NUM5MTAuMjIyMjIzIDkwMi4wOTUgOTEwLjIyMjIyMyA5NDIuNDA5MDExIDg3Ni44MDUgOTc1LjIzODA5NUwxMTMuNzc3Nzc4IDk3NS4yMzgwOTUgMTEzLjc3Nzc3OCAyNC4zODA5NTIgODguODg4ODg5IDQ4Ljc2MTkwNSA4MzUuNTUwMjcgNDguNzYxOTA1Wk02NCAwIDY0IDI0LjM4MDk1MiA2NCAxMDI0TDk2MCAxMDI0QzgzNS41NTAyNyAxMDI0IDkwNC4yNzc2MTUgMTAyNCA5NjAgOTY5LjMyNTQ5OEw5NjAgNTQuNDkyMDRDOTYwIDU0LjQ5MjA0IDkwNC4yNzc2MTUgMCA4MzUuNTUwMjcgMEw4OC44ODg4ODkgMCA2NCAwWiINCiAgICBwLWlkPSIyNDc5Ij48L3BhdGg+DQogIDxwYXRoDQogICAgZD0iTTc3NS4xNjQzNjEgMjE5LjQyODU3MkM3ODguOTEwMTE0IDIxOS40Mjg1NzIgODAwLjA1MzI1IDIwOC41MTI4NDcgODAwLjA1MzI1IDE5NS4wNDc2MTkgODAwLjA1MzI1IDE4MS41ODIzOTEgNzg4LjkxMDExNCAxNzAuNjY2NjY3IDc3NS4xNjQzNjEgMTcwLjY2NjY2N0wyNjMuMTExMTExIDE3MC42NjY2NjdDMjQ5LjM2NTM1NyAxNzAuNjY2NjY3IDIzOC4yMjIyMjIgMTgxLjU4MjM5MSAyMzguMjIyMjIyIDE5NS4wNDc2MTkgMjM4LjIyMjIyMiAyMDguNTEyODQ3IDI0OS4zNjUzNTcgMjE5LjQyODU3MiAyNjMuMTExMTExIDIxOS40Mjg1NzJMNzc1LjE2NDM2MSAyMTkuNDI4NTcyWiINCiAgICBwLWlkPSIyNDgxIj48L3BhdGg+DQogIDxwYXRoDQogICAgZD0iTTc3NS4xNjQzNjEgMzY1LjcxNDI4NUM3ODguOTEwMTE0IDM2NS43MTQyODUgODAwLjA1MzI1IDM1NC43OTg1NjIgODAwLjA1MzI1IDM0MS4zMzMzMzMgODAwLjA1MzI1IDMyNy44NjgxMDUgNzg4LjkxMDExNCAzMTYuOTUyMzgyIDc3NS4xNjQzNjEgMzE2Ljk1MjM4MkwyNjMuMTExMTExIDMxNi45NTIzODJDMjQ5LjM2NTM1NyAzMTYuOTUyMzgyIDIzOC4yMjIyMjIgMzI3Ljg2ODEwNSAyMzguMjIyMjIyIDM0MS4zMzMzMzMgMjM4LjIyMjIyMiAzNTQuNzk4NTYyIDI0OS4zNjUzNTcgMzY1LjcxNDI4NSAyNjMuMTExMTExIDM2NS43MTQyODVMNzc1LjE2NDM2MSAzNjUuNzE0Mjg1WiINCiAgICBwLWlkPSIyNDgyIj48L3BhdGg+DQogIDxwYXRoDQogICAgZD0iTTc3NS4xNjQzNjEgNTM2LjM4MDk1MUM3ODguOTEwMTE0IDUzNi4zODA5NTEgODAwLjA1MzI1IDUyNS40NjUyMjkgODAwLjA1MzI1IDUxMiA4MDAuMDUzMjUgNDk4LjUzNDc3MSA3ODguOTEwMTE0IDQ4Ny42MTkwNDkgNzc1LjE2NDM2MSA0ODcuNjE5MDQ5TDI2My4xMTExMTEgNDg3LjYxOTA0OUMyNDkuMzY1MzU3IDQ4Ny42MTkwNDkgMjM4LjIyMjIyMiA0OTguNTM0NzcxIDIzOC4yMjIyMjIgNTEyIDIzOC4yMjIyMjIgNTI1LjQ2NTIyOSAyNDkuMzY1MzU3IDUzNi4zODA5NTEgMjYzLjExMTExMSA1MzYuMzgwOTUxTDc3NS4xNjQzNjEgNTM2LjM4MDk1MVoiDQogICAgcC1pZD0iMjQ4MyI+PC9wYXRoPg0KPC9zdmc+';
var closeIcon =
  'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTQiIGhlaWdodD0iMTQiIHZpZXdCb3g9IjAgMCAxNCAxNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTAuODM4NTA5IDIuMDQwNDFDMC41NTkzMzIgMS43NjEyMyAwLjU1OTMzMyAxLjMwODYgMC44Mzg1MDkgMS4wMjk0MkMxLjExNzY5IDAuNzUwMjQ0IDEuNTcwMzIgMC43NTAyNDMgMS44NDk1IDEuMDI5NDJMMTIuOTcwNCAxMi4xNTAzQzEzLjI0OTUgMTIuNDI5NSAxMy4yNDk1IDEyLjg4MjEgMTIuOTcwNCAxMy4xNjEzQzEyLjY5MTIgMTMuNDQwNSAxMi4yMzg2IDEzLjQ0MDUgMTEuOTU5NCAxMy4xNjEzTDAuODM4NTA5IDIuMDQwNDFaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTIuOTcwNCAyLjA0MDQxQzEzLjI0OTUgMS43NjEyMyAxMy4yNDk1IDEuMzA4NiAxMi45NzA0IDEuMDI5NDJDMTIuNjkxMiAwLjc1MDI0NCAxMi4yMzg2IDAuNzUwMjQ0IDExLjk1OTQgMS4wMjk0MkwwLjgzODUwMiAxMi4xNTAzQzAuNTU5MzI1IDEyLjQyOTUgMC41NTkzMjUgMTIuODgyMSAwLjgzODUwMiAxMy4xNjEzQzEuMTE3NjggMTMuNDQwNSAxLjU3MDMxIDEzLjQ0MDUgMS44NDk0OSAxMy4xNjEzTDEyLjk3MDQgMi4wNDA0MVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=';

var videoImage =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAG4AAABYCAYAAAAUeMJLAAAABHNCSVQICAgIfAhkiAAABRBJREFUeF7tnb2rHUUYxp8H/wBjo6Wd2ARvesFYCTbJBRtBMCqmsAhGEPEDTNBCBSGQQrQxkYhCiqhNRBAjtkJu8APBSgu1EExEwaj4ynPdve7ds4dzdmZ2Z+fu+8LlwLkz884+v/l4z+zsDrHEzOwQgA0AB6vPfcvS+vdJFbgKYAvAJX2SfL+rdLa/NDPBerOClbRGXliQAoL4EEl97tgucGb2PIATQcV7pqEVOEHyZO1kB5yZCZjAuU1XgeMkT6l62+Cq4fFyR33PAjhTjbUae90GVsDMFEtoujoC4MEOdwc0bNbgBE2Ja7sG4DBJTZBumRQwMwWG7wG4sVEFBSwHaGaHAVxo1e1uh5aJVsttBe+T1tebAtee286SVDd1m4gCZqbpqjlsnhQ4DYd3NerovW0iwOpqdPS6TwXuFwDNH9c3kfRAZELwqoBFnGq7KnDWrCPJhR/lXdfQzpfiOnP5zuVXmoX6dnAR4uVsrA7OwcW3v9BhI9ZzLr8+VEaSc3CFCujgHFxvBUIbjQcnHpz0bmwLGUJbX6znXH6zBCexYnn+fgosLJSErpz0c+upYxVwcLEKZsrv4DIJH+vWwcUqmCm/g8skfKxbBxerYKb8Di6T8LFu9wQ4M7sBwG3aWUjym1hRSshfPDgzewDAqwBurgT/GcDbAJ4j+VsJEELqWDQ4M7sdwJcA1OPa9h2AoyQ/ChFm6nlKB3cewH0rRH4LwDGS2tS7Z6x0cD8BuGUNGj8CeIykdgHvCSsdnLYNNrdjr4IicI+S1DxYtM0NnGAJmobOd0omN0dwNS8FLQpeFMQUZ3MGJ1i/A3gGwGmSuzYCT53k3MHVfD4D8AjJb6cOrK6fg/uf1B8A9GjuKyT/mTpAB7dI6HMAD5P8YsrwHFw3nb8AvAzgRZLXpwiwdHDtR8JSa/wVgHtJfp+64NjyHNxqBb8GoAfk/1yddLwUDm49rdXrLq6XdJxUpYPru+QVqurTJF8KzTxEPge3nqr3k3x3vaTjpHJwq3X+FcCtU3sOvnRwQ0eVWhI7RPLj1XzHTVE6uCHnuEkvgzm4xY6iO+VPAXhjygvPDm43OG0yepKk7phP2hzcf3iK21g0d3C6C3AawLMkFYgUY3MGp9V/3QXQ3YDibI7gtNr/QnXfTXcBirS5gZt0iN+nBc0FnHZ2PQHg3JRD/DmB+xDAPSsuWCH+43thL2XzOkvvcV2vIa6vr7gQfzY9ThdqZq9rf2Tjov8G8BoA3YopKsSfFbgK3n4Ad4ojgA9I/tBHhBLTFj1Ulih4qjo7uFRKjlyOgxtZ8FTuHFwqJUcux8GNLHgqdw4ulZIjl+PgRhY8lTsHl0rJkctxcCMLnspdMnDtglJUMNcrdnP5lWahvv1l2hHi5WysDs7Bxbe/0GEj1nMuvz5URpJzcIUK6OAcXG8FQhuNBycenPRubAsZQltfrOdcfmODk/ajS364bWxLSJy/43Dba36cdGKRhyhu2XHSfoD7EGonLHPZAe5dexX9EPeEwscU1dHbVNzm9pnfZrYF4I6GA817myQvxTj1vHEKVNAuANjXKOkKyY0a3AaAyx1uzgDQnxILptvAClSBiDrRkeqv7VFvPdraBlf1uvZcN3AVvfgABY6TPLX9M6KZ2cwcXoCaI2XZgbYArup5GjY1PDbnvJHq5m46FLiiIVPDY/N/u3pcq/cp2hTEg9Vnn9fGO4FwBfT6DkFSYLi17OyEfwGiWm1shbv/BgAAAABJRU5ErkJggg==';
var parseBool = function (value, defaultValue) {
  if (value === true || value === false) return value;
  if (value == undefined || value == null) return false;
  defaultValue = !!defaultValue;

  if (typeof value == 'number') return value != 0;
  if (typeof value != 'string') return false;

  if (value == '') return false;

  var trueArr = ['true', 'yes', 'show', 'enable'],
    falseArr = ['false', 'no', 'hide', 'disable'],
    value = value.toLowerCase();

  if (trueArr.indexOf(value) < 0 && falseArr.indexOf(value) < 0) {
    return defaultValue;
  } else {
    return trueArr.indexOf(value) >= 0;
  }
};

var isIphone = function () {
  var userAgent = navigator.userAgent.toLowerCase();
  return userAgent.indexOf('iphone') > 0;
};

var isIphoneXor11 = function () {
  return (
    isIphone() &&
    ((screen.height == 812 && screen.width == 375) || (screen.height == 896 && screen.width == 414))
  );
};

// 判断条件: 根据主色调的 hsl
// h的区间：[0,49],[50,190],[191,360]
// s 不用判断
// l的区间：[0,39],[40,60],[61,100]
function getTitleColor(mainHsl) {
  let [h, s, l] = mainHsl;
  let temp = l;
  temp = 95;
  if (h > 49 && h < 191) {
    if (l > 39 && l < 101) temp = 20;
  } else {
    if (l > 60 && l < 101) temp = 20;
  }
  // if (l > 60 && l < 101) {
  //   if (h < 50 || h > 190) temp = 20;
  // }
  return [h, s, temp];
}
function hexToRgba(hex, opacity = 1) {
  if (!hex) hex = '#0076fe';
  // 处理rgba格式
  if (hex.startsWith('rgba(')) {
    const rgba = hex.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/);
    if (rgba) {
      return [parseInt(rgba[1]), parseInt(rgba[2]), parseInt(rgba[3]), parseFloat(rgba[4])];
    }
  }
  // 处理hex格式
  let r = parseInt('0x' + hex.slice(1, 3));
  let g = parseInt('0x' + hex.slice(3, 5));
  let b = parseInt('0x' + hex.slice(5, 7));
  return [r, g, b, opacity];
}
function rgbtohsl(r, g, b) {
  r = r / 255;
  g = g / 255;
  b = b / 255;

  var min = Math.min(r, g, b);
  var max = Math.max(r, g, b);
  var l = (min + max) / 2;
  var difference = max - min;
  var h, s, l;
  if (max == min) {
    h = 0;
    s = 0;
  } else {
    s = l > 0.5 ? difference / (2.0 - max - min) : difference / (max + min);
    switch (max) {
      case r:
        h = (g - b) / difference + (g < b ? 6 : 0);
        break;
      case g:
        h = 2.0 + (b - r) / difference;
        break;
      case b:
        h = 4.0 + (r - g) / difference;
        break;
    }
    h = Math.round(h * 60);
  }
  s = Math.round(s * 100); //转换成百分比的形式
  l = Math.round(l * 100);
  return [h, s, l];
}
function hsltorgb(h, s, l) {
  var h = h / 360;
  var s = s / 100;
  var l = l / 100;
  var rgb = [];

  if (s == 0) {
    rgb = [Math.round(l * 255), Math.round(l * 255), Math.round(l * 255)];
  } else {
    var q = l >= 0.5 ? l + s - l * s : l * (1 + s);
    var p = 2 * l - q;
    var tr = (rgb[0] = h + 1 / 3);
    var tg = (rgb[1] = h);
    var tb = (rgb[2] = h - 1 / 3);
    for (var i = 0; i < rgb.length; i++) {
      var tc = rgb[i];
      if (tc < 0) {
        tc = tc + 1;
      } else if (tc > 1) {
        tc = tc - 1;
      }
      switch (true) {
        case tc < 1 / 6:
          tc = p + (q - p) * 6 * tc;
          break;
        case 1 / 6 <= tc && tc < 0.5:
          tc = q;
          break;
        case 0.5 <= tc && tc < 2 / 3:
          tc = p + (q - p) * (4 - 6 * tc);
          break;
        default:
          tc = p;
          break;
      }
      rgb[i] = Math.round(tc * 255);
    }
  }
  return rgb;
}
function rgbToStr(r, g, b) {
  let rr = '00' + r.toString(16);
  let gg = '00' + g.toString(16);
  let bb = '00' + b.toString(16);

  rr = rr.substr(rr.length - 2, 2);
  gg = gg.substr(gg.length - 2, 2);
  bb = bb.substr(bb.length - 2, 2);
  return '#' + rr + gg + bb;
}

function toAbsURL(url) {
  var a = document.createElement('a');
  a.href = url;
  return a.href;
}

function changeBookResourceBaseURL(url) {
  const bookResourceBaseURL =
    (htmlConfig.loadingConfig && htmlConfig.loadingConfig.bookResourceBaseURL) ||
    window.bookResourceBaseURL ||
    '';

  const useTheFliphtml5Service = parseBool(
    htmlConfig.loadingConfig.useTheFliphtml5Service || 'false'
  );

  if (!bookResourceBaseURL) return url;
  if (useTheFliphtml5Service) return url;
  if (url.indexOf('http') >= 0) return url;
  if (url.indexOf('file:///') >= 0) return url;

  url = url.replace('../', '').replace('./', '');

  var absUrl = toAbsURL(bookResourceBaseURL + url);
  return absUrl;
}

window.waitForLoading = true;
var LoadingJS = function () {
  this.initConfig();

  this.initHtml();
  this.initCss();

  this.startLoading();
  this.onResize();

  var self = this;
  window.onresize = function () {
    self.onResize();
  };

  window.setTimeout(function () {
    window.waitForLoading = false;
  }, 250);
};

LoadingJS.prototype = {
  initConfig: function () {
    if (!window.loadingConfig && window.bookConfig && typeof window.bookConfig != 'string') {
      window.loadingConfig = window.bookConfig;
    }
    if (
      !window.loadingConfig &&
      window.htmlConfig &&
      window.htmlConfig.bookConfig &&
      typeof window.htmlConfig.bookConfig != 'string'
    ) {
      window.loadingConfig = window.htmlConfig.bookConfig;
    }
    if (window.htmlConfig && window.htmlConfig.loadingConfig) {
      window.loadingConfig = window.htmlConfig.loadingConfig;
    }
    try {
      this.loadingVideoObjectFit = loadingConfig.loadingVideoObjectFit || 'fill';
      this.loadingBackgroundColorType = loadingConfig.loadingBackgroundColorType || '1';
      this.loadingCaptionColor = loadingConfig.loadingCaptionColor || '#DDDDDD';
      this.loadingBackground = loadingConfig.loadingBackground || '#1F2232';
      this.loadingMainColor = loadingConfig.loadingMainColor || loadingConfig.loadingBackground;
      let [mainR, mainG, mainB, mainA] = hexToRgba(this.loadingMainColor);
      if (this.loadingBackgroundColorType == 0) {
        let [mainH, mainS, mainL] = rgbtohsl(mainR, mainG, mainB);
        let [titleH, titleS, titleL] = getTitleColor([mainH, mainS, mainL]);
        this.loadingBackground = `hsla(${mainH},${mainS}%,${mainL}%,${mainA})`;
        this.loadingCaptionColor = `hsla(${titleH},${titleS}%,${titleL}%,${mainA})`;
        loadingConfig.loadingCaptionColor = this.loadingCaptionColor;
      }
      this.loadingCaption = loadingConfig.loadingCaption || document.title;
      this.loadingCaptionFontSize = !isNaN(loadingConfig.loadingCaptionFontSize)
        ? parseInt(loadingConfig.loadingCaptionFontSize)
        : 28;
      this.loadingCaptionFontSize = Math.min(100, this.loadingCaptionFontSize);
      this.loadingCaptionFontSize = Math.max(0, this.loadingCaptionFontSize);
      this.loadingPicture = loadingConfig.loadingPicture
        ? changeBookResourceBaseURL(loadingConfig.loadingPicture) + '?' + new Date().getTime()
        : '';
      //   this.loadingPictureWidth = !isNaN(loadingConfig.loadingPictureWidth) ? parseInt(loadingConfig.loadingPictureWidth) : 80;
      this.loadingPictureHeight = !isNaN(loadingConfig.loadingPictureHeight)
        ? parseInt(loadingConfig.loadingPictureHeight)
        : 150;
      this.loadingDisplayTime = !isNaN(loadingConfig.loadingDisplayTime)
        ? parseInt(loadingConfig.loadingDisplayTime)
        : 0;
      this.loadingSpacing = !isNaN(loadingConfig.loadingSpacing)
        ? parseInt(loadingConfig.loadingSpacing)
        : 20;
      this.showLoadingGif =
        loadingConfig.showLoadingGif != undefined
          ? parseBool(loadingConfig.showLoadingGif, true)
          : true;
      this.loadingVideo = loadingConfig.loadingVideo
        ? changeBookResourceBaseURL(loadingConfig.loadingVideo) + '?' + new Date().getTime()
        : '';
      if (this.loadingVideo) this.showLoadingGif = false;
      this.isCloseVideoButtonVisible = loadingConfig.isCloseVideoButtonVisible
        ? parseBool(loadingConfig.isCloseVideoButtonVisible, false)
        : false;
      this.loadingVideoCover = loadingConfig.loadingVideoCover
        ? changeBookResourceBaseURL(loadingConfig.loadingVideoCover) + '?' + new Date().getTime()
        : '';
      this.alwaysDoublePageOnMobile = loadingConfig.alwaysDoublePageOnMobile
        ? parseBool(loadingConfig.alwaysDoublePageOnMobile, false)
        : false;
    } catch (err) {
      this.loadingCaption = document.title;
      this.loadingCaptionFontSize = 28;
      this.loadingCaptionColor = '#DDDDDD';
      this.loadingBackground = '#1F2232';
      this.loadingPicture = '';
      this.showLoadingGif = true;
      //   this.loadingPictureWidth = 80;
      this.loadingPictureHeight = 150;
      this.loadingDisplayTime = 0;
      this.loadingSpacing = 20;
      this.loadingVideo = '';
      this.loadingVideoObjectFit = 'fill';
      this.isCloseVideoButtonVisible = false;
      this.loadingVideoCover = '';
      this.alwaysDoublePageOnMobile = false;
    }

    if (document.querySelector('#tmpLoading')) {
      document.querySelector('#tmpLoading').style.background = this.loadingBackground;
    }
  },
  get upsideOnMobile() {
    if (this.alwaysDoublePageOnMobile && window.innerHeight > window.innerWidth) return true;
    return false;
  },
  initHtml: function () {
    this.stop = false;

    this.instance = document.createElement('div');
    this.title = document.createElement('p');
    this.title.innerHTML = this.loadingCaption;
    this.copyright = document.createElement('p');
    this.copyright.setAttribute(
      'style',
      'position:absolute;bottom:5%;font-size:1.1rem;width:100%;text-align:center;'
    );
    if (window.userInfo && window.userInfo.copyright)
      this.copyright.innerHTML = window.userInfo.copyright;

    this.bg = document.createElement('div');
    this.bg.setAttribute('style', 'transform:scale(1);');

    this.loadingAnimation = true;
    if (this.showLoadingGif) this.initAnimationHtml();

    if (this.loadingVideo) {
      this.initVideo();
    }

    if (this.loadingPicture) {
      this.image = document.createElement('img');
      this.image.src = this.loadingPicture;
      this.instance.appendChild(this.image);
    }
    this.instance.appendChild(this.title);
    this.instance.appendChild(this.copyright);
    this.bg.appendChild(this.instance);
    document.body.appendChild(this.bg);

    try {
      if (document.getElementById('bacgradient_image'))
        document.getElementById('bacgradient_image').style.visibility = 'visible';
    } catch (error) {}
  },

  initVideo: function () {
    this.videoImage = document.createElement('img');
    this.videoImage.src = videoImage;
    this.videoImage.setAttribute(
      'style',
      'position: absolute; top: 0px; right: 0px;left:0;bottom:0;margin:auto; z-index: 4;'
    );
    this.videoImage.addEventListener(
      'click',
      function () {
        this.video.style.display = 'block';
        this.instance.style.display = 'none';
        this.video.play();
      }.bind(this)
    );

    this.videoLoadImage = document.createElement('div');
    this.videoLoadImage.setAttribute(
      'style',
      'position: absolute; top: auto; right: 0px;left:0;bottom:50%;margin:auto; z-index: 4;'
    );
    this.videoLoadImage.setAttribute('class', 'loader');

    this.video = document.createElement('video');
    this.video.src = this.loadingVideo;
    // this.video.setAttribute("webkit-playsinline", "true");
    // this.video.setAttribute("x5-playsinline", "true");
    // this.video.setAttribute("playsinline", "");
    this.video.setAttribute('preload', 'auto');
    if (this.loadingVideoCover) this.video.setAttribute('poster', this.loadingVideoCover);
    // this.video.setAttribute("controls", "controls");
    // this.video.setAttribute("muted", "");
    // this.video.setAttribute("x-webkit-airplay", "allow");
    this.video.setAttribute(
      'style',
      'width: 100%; height: 100%; object-fit: ' +
        this.loadingVideoObjectFit +
        '; position: absolute; top: 0px; right: 0px; z-index: 3;'
    );
    this.video.addEventListener('play', () => {
      if (this.isCloseVideoButtonVisible) this.createCloseBtn();
    });

    this.bg.appendChild(this.video);
    this.instance.appendChild(this.videoImage);
    this.instance.appendChild(this.videoLoadImage);
    this.video.load();
  },

  createCloseBtn: function () {
    this.videoCloseBtn = document.createElement('div');
    const closeImg = document.createElement('img');
    closeImg.src = closeIcon;
    closeImg.setAttribute('style', 'width: 16px; height: 16px;');

    this.videoCloseBtn.appendChild(closeImg);
    this.videoCloseBtn.setAttribute(
      'style',
      'position: absolute; top: 10px; right: 40px; z-index: 999;background: rgba(0, 0, 0, 0.5); border-radius: 50%;height: 30px; width: 30px;cursor: pointer;box-sizing: border-box;display:flex;justify-content:center;align-items:center;'
    );
    this.videoCloseBtn.addEventListener('click', () => this.video.pause());
    this.bg.appendChild(this.videoCloseBtn);
  },

  showVideo: function () {
    try {
      this.videoImage.style.display = 'block';
      this.videoLoadImage.style.display = 'none';
    } catch (error) {}
  },

  loadingSvg: function () {
    // this.loadImageUrl = "<svg t=\"1525916222299\" class=\"icon\" style=\"\" viewBox=\"130 0 800 1024\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" p-id=\"2478\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" width=\"49\" height=\"56\"><defs><style type=\"text/css\"></style></defs><path d=\"M835.55027 48.761905C876.805122 48.761905 910.222223 81.441158 910.222223 121.753604L910.222223 902.095C910.222223 902.095 910.222223 942.409011 876.805 975.238095L113.777778 975.238095 113.777778 24.380952 88.888889 48.761905 835.55027 48.761905ZM64 0 64 24.380952 64 1024L960 1024C835.55027 1024 904.277615 1024 960 969.325498L960 54.49204C960 54.49204 904.277615 0 835.55027 0L88.888889 0 64 0Z\" p-id=\"2479\"></path><path d=\"M775.164361 219.428572C788.910114 219.428572 800.05325 208.512847 800.05325 195.047619 800.05325 181.582391 788.910114 170.666667 775.164361 170.666667L263.111111 170.666667C249.365357 170.666667 238.222222 181.582391 238.222222 195.047619 238.222222 208.512847 249.365357 219.428572 263.111111 219.428572L775.164361 219.428572Z\" p-id=\"2481\"></path><path d=\"M775.164361 365.714285C788.910114 365.714285 800.05325 354.798562 800.05325 341.333333 800.05325 327.868105 788.910114 316.952382 775.164361 316.952382L263.111111 316.952382C249.365357 316.952382 238.222222 327.868105 238.222222 341.333333 238.222222 354.798562 249.365357 365.714285 263.111111 365.714285L775.164361 365.714285Z\" p-id=\"2482\"></path><path d=\"M775.164361 536.380951C788.910114 536.380951 800.05325 525.465229 800.05325 512 800.05325 498.534771 788.910114 487.619049 775.164361 487.619049L263.111111 487.619049C249.365357 487.619049 238.222222 498.534771 238.222222 512 238.222222 525.465229 249.365357 536.380951 263.111111 536.380951L775.164361 536.380951Z\" p-id=\"2483\"></path></svg>";

    var loadImageUrl = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    loadImageUrl.setAttribute('t', '1525916222299');
    loadImageUrl.setAttribute('class', 'icon');
    loadImageUrl.setAttribute('style', '');
    loadImageUrl.setAttribute('viewBox', '130 0 800 1024');
    loadImageUrl.setAttribute('version', '1.1');
    loadImageUrl.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    loadImageUrl.setAttribute('p-id', '2478');
    loadImageUrl.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
    loadImageUrl.setAttribute('width', '49');
    loadImageUrl.setAttribute('height', '56');

    var defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    var style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    style.setAttribute('type', 'text/css');
    var path1 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path1.setAttribute(
      'd',
      'M835.55027 48.761905C876.805122 48.761905 910.222223 81.441158 910.222223 121.753604L910.222223 902.095C910.222223 902.095 910.222223 942.409011 876.805 975.238095L113.777778 975.238095 113.777778 24.380952 88.888889 48.761905 835.55027 48.761905ZM64 0 64 24.380952 64 1024L960 1024C835.55027 1024 904.277615 1024 960 969.325498L960 54.49204C960 54.49204 904.277615 0 835.55027 0L88.888889 0 64 0Z'
    );
    path1.setAttribute('p-id', '2479');

    var path2 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path2.setAttribute(
      'd',
      'M775.164361 219.428572C788.910114 219.428572 800.05325 208.512847 800.05325 195.047619 800.05325 181.582391 788.910114 170.666667 775.164361 170.666667L263.111111 170.666667C249.365357 170.666667 238.222222 181.582391 238.222222 195.047619 238.222222 208.512847 249.365357 219.428572 263.111111 219.428572L775.164361 219.428572Z'
    );
    path2.setAttribute('p-id', '2481');

    var path3 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path3.setAttribute(
      'd',
      'M775.164361 365.714285C788.910114 365.714285 800.05325 354.798562 800.05325 341.333333 800.05325 327.868105 788.910114 316.952382 775.164361 316.952382L263.111111 316.952382C249.365357 316.952382 238.222222 327.868105 238.222222 341.333333 238.222222 354.798562 249.365357 365.714285 263.111111 365.714285L775.164361 365.714285Z'
    );
    path3.setAttribute('p-id', '2482');

    var path4 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path4.setAttribute(
      'd',
      'M775.164361 536.380951C788.910114 536.380951 800.05325 525.465229 800.05325 512 800.05325 498.534771 788.910114 487.619049 775.164361 487.619049L263.111111 487.619049C249.365357 487.619049 238.222222 498.534771 238.222222 512 238.222222 525.465229 249.365357 536.380951 263.111111 536.380951L775.164361 536.380951Z'
    );
    path4.setAttribute('p-id', '2483');

    defs.appendChild(style);
    loadImageUrl.appendChild(defs);
    loadImageUrl.appendChild(path1);
    loadImageUrl.appendChild(path2);
    loadImageUrl.appendChild(path3);
    loadImageUrl.appendChild(path4);

    return loadImageUrl;
  },
  createLoadingImg: function (src) {
    var preIcon = document.createElement('img');
    preIcon.src = src;
    return preIcon;
  },
  changeLoadingImgColor: function (base64Data, newColor) {
    return new Promise((resolve, reject) => {
      const img = new Image();

      img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);

        ctx.globalCompositeOperation = 'source-in';
        ctx.fillStyle = newColor;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const modifiedBase64Data = canvas.toDataURL();
        resolve(modifiedBase64Data);
      };

      img.onerror = error => {
        console.log(error);
      };

      img.src = base64Data;
    });
  },
  initAnimationHtml: async function () {
    if (!document.createElementNS) return;
    this.loadBox = document.createElement('div');
    var loadingIcon = await this.changeLoadingImgColor(loadingIconBase64, this.loadingCaptionColor);
    var img1 = this.createLoadingImg(loadingIcon);
    var img2 = this.createLoadingImg(loadingIcon);
    this.img3 = this.createLoadingImg(loadingIcon);

    if (isIphoneXor11()) {
      var self = this;
      var index = 0;
      this.loadingAnimation = true;
      var render = function () {
        window.setTimeout(function () {
          if (!self.loadingAnimation) return;
          try {
            var rotateY = 1.8 * index;
            var tmpStyle = 'rotateY(-' + rotateY + 'deg) scale(0.8)';
            self.img3.style['-webkit-transform'] = tmpStyle;
            self.img3.style['-o-transform'] = tmpStyle;
            self.img3.style['-ms-transform'] = tmpStyle;
            self.img3.style['-moz-transform'] = tmpStyle;
            self.img3.style['transform'] = tmpStyle;
            index++;
            index = index % 101;
            render();
          } catch (error) {}
        }, 7);
      };
      render();
    } else {
      this.img3.setAttribute('class', 'loadingRun');
    }

    this.img3.setAttribute(
      'style',
      'position : absolute;' +
        'left : 20px;' +
        'top : 0;' +
        // "z-index : -1;" +
        '-webkit-transform:rotateY(0deg) scale(0.8);' +
        '-o-transform:rotateY(0deg) scale(0.8);' +
        '-ms-transform:rotateY(0deg) scale(0.8);' +
        '-moz-transform:rotateY(0deg) scale(0.8);' +
        'transform:rotateY(0deg) scale(0.8);' +
        '-webkit-transform-origin : 0 0;' +
        '-o-transform-origin : 0 0;' +
        '-ms-transform-origin : 0 0;' +
        '-moz-transform-origin : 0 0;' +
        'transform-origin : 0 0;' +
        'will-change : transform;' +
        'fill :' +
        this.loadingCaptionColor +
        ';'
    );

    img2.setAttribute(
      'style',
      'position : absolute;' +
        'left : 20px;' +
        'top : 0;' +
        '-webkit-transform:rotateY(180deg) scale(0.8);' +
        '-o-transform:rotateY(180deg) scale(0.8);' +
        '-ms-transform:rotateY(180deg) scale(0.8);' +
        '-moz-transform:rotateY(180deg) scale(0.8);' +
        'transform:rotateY(180deg) scale(0.8);' +
        '-webkit-g : 0 0;' +
        '-o-transform-origin : 0 0;' +
        '-ms-transform-origin : 0 0;' +
        '-moz-transform-origin : 0 0;' +
        'transform-origin : 0 0;' +
        'fill : ' +
        this.loadingCaptionColor +
        ';'
    );

    img1.setAttribute(
      'style',
      'position : absolute;' +
        'left : 20px;' +
        'top : 0;' +
        '-webkit-transform:rotateY(0) scale(0.8);' +
        '-o-transform:rotateY(0) scale(0.8);' +
        '-ms-transform:rotateY(0) scale(0.8);' +
        '-moz-transform:rotateY(0) scale(0.8);' +
        'transform:rotateY(0) scale(0.8);' +
        '-webkit-transform-origin : 0 0;' +
        '-o-transform-origin : 0 0;' +
        '-ms-transform-origin : 0 0;' +
        '-moz-transform-origin : 0 0;' +
        'transform-origin : 0 0;' +
        'fill : ' +
        this.loadingCaptionColor +
        ';'
    );

    this.loadBox.appendChild(img1);
    this.loadBox.appendChild(img2);
    this.loadBox.appendChild(this.img3);
    this.instance.appendChild(this.loadBox);
  },

  startLoading: function () {},

  destroy: function () {
    // video1.removeEventListener('ended',this);

    //                     $('.shade').hide();
    //                     $("#videoID").hide();
    //                     $(".flipbook").show();
    //                     // $('.flipbook-viewport').show();
    //                     $('.flipbook-viewport').fadeIn(3000);
    //                     $('#musicinfo').show();
    if (global.isIE8() || global.isIE9()) {
      $(this.bg).animate(
        { opacity: '0' },
        0.6,
        function () {
          this.loadingAnimation = false;
          $('body>style').html('');
          $(this.bg).remove();
          $('body').css({ 'background-color': '' });
        }.bind(this)
      );
    } else {
      animateOnce(
        $(this.bg),
        { opacity: '0' },
        0.6,
        function () {
          this.loadingAnimation = false;
          $(this.img3).attr('class', '');
          $('body>style').html('');
          $(this.bg).remove();
          $(this.image).attr('src', '');
          $('body').css({ 'background-color': '' });
        }.bind(this)
      );
      window.setTimeout(
        function () {
          if (!this.loadingAnimation) return;
          this.loadingAnimation = false;
          $(this.img3).attr('class', '');
          $('body>style').html('');
          $(this.bg).remove();
          $(this.image).attr('src', '');
          $('body').css({ 'background-color': '' });
        }.bind(this),
        1000
      );
    }
  },

  initCss: function () {
    document
      .getElementsByTagName('html')[0]
      .setAttribute('style', 'margin : 0;' + 'padding : 0;' + 'width : 100%;' + 'height : 100%;');

    document.body.setAttribute(
      'style',
      'margin : 0;' +
        'padding : 0;' +
        'width : 100%;' +
        'height : 100%;' +
        'position : fixed;' +
        'background-color :' +
        this.loadingBackground +
        ';'
    );

    var width = this.upsideOnMobile ? window.innerHeight : '100%';
    var height = this.upsideOnMobile ? window.innerWidth : '100%';
    this.bg.setAttribute(
      'style',
      'margin : 0;' +
        'padding : 0;' +
        'width : 100%;' +
        'height : 100%;' +
        'position : fixed;' +
        'background-color:' +
        this.loadingBackground +
        ';' +
        'width : ' +
        width +
        'px;' +
        'height : ' +
        height +
        'px;' +
        'transform-origin : 0px 0px;' +
        `transform : translate(${height}px) rotate(${this.upsideOnMobile ? 90 : 0}deg);`
    );

    this.instance.setAttribute(
      'style',
      'width : 100%;' +
        'height : 100%;' +
        'opacity : 1;' +
        'color :' +
        this.loadingCaptionColor +
        ';' +
        'text-align : center;' +
        'vertical-align : middle;' +
        'font-family : Tahoma;' +
        'position : relative;'
    );

    var titleTran = 'translate(-50%, ' + this.loadingSpacing / 2 + 'px)';
    var loadingBoxTran = 'translate(-50% , -' + (this.loadingSpacing / 2 + 45) + 'px)';
    var videoImageTran = 'translate(-50% , -' + (this.loadingSpacing / 2 + 60) + 'px)';
    // var videoLoadImageTran = "translate(-50% , -" + (this.loadingSpacing/2 + 60) + "px)";
    var imageTran = 'translate(-50% , 0)';
    var imageTop = '5%';
    var imageDisplay = 'block';

    var loadingPictureHeight = Math.min(window.innerHeight * 0.35, this.loadingPictureHeight);
    if (this.showLoadingGif || this.loadingVideo) {
      if (window.innerHeight <= 300) imageDisplay = 'none';
    } else {
      imageTop = '50%';
      imageTran = 'translate(-50% , -' + (this.loadingSpacing / 2 + loadingPictureHeight) + 'px)';
    }

    if (this.image)
      this.image.setAttribute(
        'style',
        'display : ' +
          imageDisplay +
          ';' +
          'position : absolute;' +
          'top : ' +
          imageTop +
          ';' +
          'left : 50%;' +
          '-webkit-transform :' +
          imageTran +
          ';' +
          '-moz-transform :' +
          imageTran +
          ';' +
          '-ms-transform :' +
          imageTran +
          ';' +
          '-o-transform :' +
          imageTran +
          ';' +
          'transform :' +
          imageTran +
          ';' +
          // "width : " + this.loadingPictureWidth + "px;" +
          'height : ' +
          this.loadingPictureHeight +
          'px;' +
          'max-width : 90%;' +
          'max-height : 35%;'
      );

    this.title.setAttribute(
      'style',
      'font-family:Arial,Helvetica,sans serif;' +
        'font-size : ' +
        this.loadingCaptionFontSize +
        'px;' +
        'position : absolute;' +
        'top : 50%;' +
        'left : 50%;' +
        '-webkit-transform :' +
        titleTran +
        ';' +
        '-moz-transform :' +
        titleTran +
        ';' +
        '-ms-transform :' +
        titleTran +
        ';' +
        '-o-transform :' +
        titleTran +
        ';' +
        'transform :' +
        titleTran +
        ';' +
        'margin : 0;' +
        'padding : 0;' +
        'width:90%;'
    );

    if (this.videoImage)
      this.videoImage.setAttribute(
        'style',
        'position:relative;' +
          'cursor:pointer;' +
          'display:none;' +
          'z-index:4;' +
          'perspective:200px;' +
          '-webkit-transform-style:preserve-3d;' +
          '-o-transform-style:preserve-3d;' +
          '-ms-transform-style:preserve-3d;' +
          '-moz-transform-style:preserve-3d;' +
          'transform-style:preserve-3d;' +
          'position : absolute;' +
          'width : auto;' +
          'height : 60px;' +
          'left : 50%;' +
          'top : 50%;' +
          '-webkit-transform : ' +
          videoImageTran +
          ';' +
          '-moz-transform : ' +
          videoImageTran +
          ';' +
          '-ms-transform : ' +
          videoImageTran +
          ';' +
          '-o-transform : ' +
          videoImageTran +
          ';' +
          'transform : ' +
          videoImageTran +
          ';' +
          'padding : 0;'
      );

    if (this.videoLoadImage)
      this.videoLoadImage.style['margin-bottom'] = this.loadingSpacing / 2 + 'px';

    if (this.loadBox)
      this.loadBox.setAttribute(
        'style',
        'position:relative;' +
          'perspective:200px;' +
          '-webkit-transform-style:preserve-3d;' +
          '-o-transform-style:preserve-3d;' +
          '-ms-transform-style:preserve-3d;' +
          '-moz-transform-style:preserve-3d;' +
          'transform-style:preserve-3d;' +
          'position : absolute;' +
          'width : 39.2px;' +
          'height : 44.8px;' +
          'left : 50%;' +
          'top : 50%;' +
          '-webkit-transform : ' +
          loadingBoxTran +
          ';' +
          '-moz-transform : ' +
          loadingBoxTran +
          ';' +
          '-ms-transform : ' +
          loadingBoxTran +
          ';' +
          '-o-transform : ' +
          loadingBoxTran +
          ';' +
          'transform : ' +
          loadingBoxTran +
          ';' +
          'padding : 0;'
      );
  },

  onResize: function () {}
};

var jsLoadingBar = null;

function GuideLoadingJS() {
  this.initConfig();
  this.initHtml();
  this.initCss();
  queueMicrotask(this.onResize.bind(this));
  this.setLoading(true);
}

(function (g) {
  function _get(obj, path, defaultValue) {
    const pathArray = Array.isArray(path) ? path : path.split('.');
    for (let i = 0; i < pathArray.length; i++) {
      if (!obj || !obj.hasOwnProperty(pathArray[i])) {
        return defaultValue;
      }
      obj = obj[pathArray[i]];
      if (obj === '') {
        if (defaultValue) return defaultValue;
        else return '';
      }
    }
    return obj;
  }

  function initStyleSheet(id) {
    var sheet = null;
    var el = document.createElement('style');
    el.id = id;
    document.head.append(el);
    for (var s of document.styleSheets) {
      if (s.ownerNode === el) {
        sheet = s;
      }
    }
    return function () {
      return sheet;
    };
  }

  var getStyleSheet = initStyleSheet('loading');

  function updateStyleSheet(selectorText, kv, mediaQuery, _sheet) {
    var sheet = _sheet ? _sheet : getStyleSheet();
    var rules = sheet.cssRules;
    var rule = null;
    for (var r of rules) {
      if (r.selectorText === selectorText) {
        rule = r;
        break;
      }
    }
    if (mediaQuery && mediaQuery !== '') {
      var index = sheet.insertRule(`${mediaQuery} { ${selectorText} {} }`, rules.length);
      rule = rules.item(index);
      updateStyleSheet(selectorText, kv, null, rule);
    } else {
      if (!rule && selectorText) {
        var index = sheet.insertRule(`${selectorText} {}`, rules.length);
        rule = rules.item(index);
      }
      for (var k in kv) {
        var v = kv[k];
        if (v && v === '') {
          rule.style.removeProperty(k);
        } else if (typeof v === 'object') {
          updateStyleSheet(
            `${selectorText}${k.startsWith(':') ? '' : ' '}${k}`,
            v,
            mediaQuery,
            _sheet
          );
        } else {
          var p = String(v).split(' !');
          rule.style.setProperty(k, p[0], p[1]);
        }
      }
    }
  }

  var isPhone = function () {
    var search = window.location.search;

    if (
      /mobile|phone/i.test(navigator.userAgent) ||
      (window._agent_ && _agent_.browser.device == _agent_.browser.DEVICE_PHONE)
    ) {
      return true;
    }
    if (
      search.toLowerCase().indexOf('phone=true') >= 0 ||
      search.toLowerCase().indexOf('phonebook=true') >= 0
    ) {
      return true;
    }
    return false;
  };

  function isIOS() {
    const isIOS = /iP(hone|od|ad)/.test(navigator.userAgent);
    return isIOS;
  }
  function removeStartFrom(url, str) {
    if (typeof str != 'string') return;
    if (!str) return;

    var startIndex = url.indexOf(str);
    if (startIndex < 0) return url;

    return url.substring(0, startIndex) + '';
  }

  function getMeta(metaName) {
    var origin = window.location.origin;
    var pathname = window.location.pathname;
    var url = window.location.href;
    var shotUrl;
    if (url.indexOf('mobile') > -1) {
      shotUrl = removeStartFrom(url, '/mobile/');
      shotUrl = removeStartFrom(shotUrl, '/articles/');
      shotUrl += '/files/shot.jpg';
    } else {
      if (pathname.indexOf('index.html') > -1) {
        pathname = pathname.replace('index.html', '');
      }
      shotUrl = origin + pathname + 'files/shot.jpg';
    }
    return shotUrl;
  }

  function debounce(fn, d = 16) {
    var timer = null;
    return () => {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
      timer = setTimeout(fn.bind(this, ...arguments), d);
    };
  }
  GuideLoadingJS.prototype = {
    BODY: 'boot',
    CONTENT: 'boot_content',
    IMG: 'boot_cover',
    TEXT: 'boot_title',
    BUTTON: 'boot_button',
    BRAND: 'boot_brand',
    DIVIDER: 'boot_divider',
    CARD: 'boot_card',
    POP: 'popContact',
    // isMobile: /mobile|phone/i.test(navigator.userAgent),
    isMobile: /mobile|phone|android/i.test(navigator.userAgent),
    isIOS: isIOS(),
    _visible: true,
    config: _get(g, 'htmlConfig.loadingConfig', {}),
    get visible() {
      return this._visible;
    },
    get upsideOnMobile() {
      const alwaysDoublePageOnMobile = parseBool(this.config.alwaysDoublePageOnMobile);

      if (alwaysDoublePageOnMobile && window.innerHeight > window.innerWidth) return true;
      return false;
    },
    get loadingBackground() {
      let backgroundColor = _get(loadingConfig, 'loadingBackground', '#232D37');
      const type = _get(loadingConfig, 'loadingBackgroundColorType', '1');
      if (type == 0) {
        backgroundColor = _get(loadingConfig, 'loadingMainColor', '#232D37');
      }
      return backgroundColor;
    },
    set visible(val) {
      this._visible = val;
      updateStyleSheet(`#${this.BODY}`, {
        display: val ? 'inline-flex' : 'none'
      });
    },
    initConfig: LoadingJS.prototype.initConfig.bind(this),
    initCss() {
      var loadingConfig = this.config;
      var css = {
        [`#${this.BODY}`]: {
          background: this.loadingBackground,
          display: 'inline-flex',
          position: 'absolute',
          top: 0,
          bottom: 0,
          left: 0,
          right: 0,
          transform: `translateX(${isPhone() || this.isMobile ? '-100%' : '0%'})`,
          'justify-content': 'center',
          'align-items': 'center',
          'z-index': '200'
        },
        [`#${this.CONTENT}`]: {
          display: 'flex',
          'flex-direction': 'column',
          'align-items': 'center',
          width: 'calc(100% - 32px)'
        },
        [`#${this.IMG}`]: {
          width: '224px',
          'border-radius': '8px'
          // 'min-height': `${224 * 1.38}px`
        },
        [`#${this.IMG} img`]: {
          width: '100%',
          'max-height': '322px',
          'border-radius': '10px',
          'object-fit': 'contain'
        },
        [`#${this.TEXT}`]: {
          width: 'calc(100% - 40px)',
          'max-width': '387px',
          color: _get(loadingConfig, 'loadingCaptionColor', '#FFF'),
          overflow: 'hidden',
          display: '-webkit-box',
          // 'font-size': _get(loadingConfig, 'loadingCaptionFontSize', 16) + 'px',
          'font-size': '19px',
          'margin-top': '20px',
          'text-align': 'center',
          'font-weight': 'bold',
          'text-overflow': 'ellipsis',
          '-webkit-line-clamp': 2,
          '-webkit-box-orient': 'vertical'
        },
        [`#${this.BUTTON}`]: {
          width: 'calc(100% - 40px)',
          'max-width': '387px',
          height: '46px',
          'border-radius': '74px',
          'margin-top': '29px',
          background: '#0076FE',
          display: 'flex',
          cursor: 'pointer',
          'align-items': 'center',
          'justify-content': 'center'
        },
        [`#${this.BUTTON} > svg`]: {
          width: '21px', //25
          height: '21px', //25
          animation: 'spin 2s linear infinite',
          'margin-right': '7px'
        },
        [`#${this.BUTTON} > span`]: {
          color: '#fff',
          'font-size': '15px',
          'font-weight': 'bold'
        },
        [`#${this.BRAND}`]: {
          width: '100%',
          'max-width': '387px',
          display: 'flex',
          color: '#76838F',
          'flex-flow': 'column',
          'align-items': 'center',
          'font-size': '14px',
          'margin-top': '54px'
        },
        [`#${this.DIVIDER}`]: {
          display: 'flex',
          width: '100%',
          'box-sizing': 'border-box',
          'align-items': 'center',
          'justify-content': 'center'
        },
        [`#${this.DIVIDER} i`]: {
          display: 'inline-block',
          width: '100%',
          height: '1px',
          background: '#76838F'
        },
        [`#${this.DIVIDER} span`]: {
          margin: '0 14px',
          'white-space': 'nowrap'
        },
        [`#${this.CARD}`]: {
          // width: '422px',
          width: '100%',
          height: '96px',
          padding: '0 18px',
          'border-radius': '11px',
          'margin-top': '16px',
          background: '#303943B3',
          display: 'flex',
          'box-sizing': 'border-box',
          'align-items': 'center'
          // 'justify-content': 'center',
        },
        [`#${this.CARD} div`]: {
          width: 'calc(100% - 60px)'
        },
        [`#${this.CARD} img`]: {
          width: '60px',
          height: '60px',
          background: '#fff',
          'object-fit': 'contain',
          'border-radius': '100%',
          'margin-right': '5px'
        },
        [`#${this.CARD} h2`]: {
          width: '100%',
          color: '#fff',
          overflow: 'hidden',
          margin: 0,
          'font-size': '15px',
          'font-weight': 'bold',
          'text-overflow': 'ellipsis',
          'white-space': 'nowrap'
        },
        [`#${this.CARD} span`]: {
          display: 'block',
          width: '100%',
          color: '#fff',
          overflow: 'hidden',
          'font-size': '14px',
          'text-overflow': 'ellipsis',
          'white-space': 'nowrap'
        },
        [`#${this.POP}`]: {
          position: 'fixed',
          top: 0,
          bottom: 0,
          left: 0,
          right: 0,
          display: 'none',
          [`.${this.POP}Mask`]: {
            background: 'rgba(0,0,0,0.5)',
            width: '100%',
            height: '100%'
          },
          [`.${this.POP}Contain`]: {
            transition: 'transform 0.3s',
            transform: `translateY(${this.upsideOnMobile ? g.innerWidth : g.innerHeight}px)`
          },
          section: {
            width: '100%',
            background: '#F6F7FB',
            position: 'absolute',
            bottom: 0,
            'border-radius': '18px 18px 0 0',
            'padding-top': '14px'
          },
          [`.${this.POP}Title`]: {
            display: 'flex',
            color: '#000',
            padding: '0 24px',
            'justify-content': 'center',
            'align-items': 'center',
            'font-weight': 'bold',
            'font-size': '15px',
            i: {
              width: '100%',
              height: '1px',
              background: '#EAEAEA'
            },
            span: {
              display: 'block',
              padding: '0 20px',
              'white-space': 'nowrap'
            },
            svg: {
              width: '14px',
              height: '14px',
              transform: 'rotate(90deg)'
            }
          },
          [`.${this.POP}Content`]: {
            display: 'grid',
            margin: '14px 0 0',
            overflow: 'hidden scroll',
            'grid-template-columns': 'repeat(6, 64px)',
            'justify-content': 'space-around',
            'row-gap': '12px',
            [`.${this.POP}Item`]: {
              width: '58px',
              display: 'flex',
              'flex-flow': 'column',
              'justify-content': 'center',
              '*': {
                'pointer-events': 'none'
              },
              div: {
                display: 'flex',
                width: '58px',
                height: '58px',
                background: '#FFF',
                'justify-content': 'center',
                'align-items': 'center',
                'border-radius': '16px'
              },
              img: {
                width: '32px',
                'object-fit': 'contain'
              },
              span: {
                color: '#818792',
                'font-size': '12px',
                'margin-top': '5px',
                'text-align': 'center'
              }
            }
          },
          [`.${this.POP}Cancel`]: {
            margin: '14px',
            display: 'flex',
            background: '#FFF',
            height: '40px',
            color: '#000',
            'border-radius': '10px',
            'font-size': '12px',
            'align-items': 'center',
            'justify-content': 'center'
          },
          [`.${this.POP}Qrcode`]: {
            padding: 0,
            display: 'none',
            [`.${this.POP}Title`]: {
              'justify-content': 'unset',
              padding: 0,
              svg: {
                padding: '18px'
              }
            },
            [`.${this.POP}QrcodeImg`]: {
              width: '166px',
              height: '166px',
              margin: '20px auto 0',
              padding: '12px',
              background: '#FFF',
              'box-sizing': 'border-box',
              'border-radius': '10px',
              img: {
                width: '100%',
                height: '100%'
              }
            },
            p: {
              width: '160px',
              color: '#333',
              margin: '0 auto',
              background: '#FFF',
              padding: '10px 0',
              'font-size': '14px',
              'font-weight': 'bold',
              'border-radius': '30px',
              'text-align': 'center'
            }
          }
        }
      };
      var mobile_css = {
        [`#${this.BODY}`]: {
          [`.${this.CONTENT}Landscape`]: {
            [`#${this.IMG}box`]: {
              height: '96px',
              'flex-flow': 'row'
            },
            [`#${this.IMG}`]: {
              height: '96px',
              display: 'flex',
              width: 'unset',
              'justify-content': 'center',
              'min-width': '96px',
              'min-height': 'unset'
            },
            [`#${this.IMG} img`]: {
              width: 'unset',
              height: '100%'
            },
            [`#${this.TEXT}`]: {
              'margin-top': '16px'
            },
            [`#${this.BUTTON}`]: {
              'margin-top': '8px'
            }
          },
          [`#${this.BRAND}Landscape`]: {
            display: 'flex',
            overflow: 'hidden',
            'margin-left': '12px',
            'align-items': 'center',
            [`#${this.BRAND}`]: {
              width: '255px',
              'margin-top': 0
            },
            [`#${this.DIVIDER}`]: {
              padding: '0 26px'
            },
            [`#${this.CARD}`]: {
              width: '100%',
              'margin-top': '8px',
              h2: {
                width: '172px'
              },
              span: {
                width: '172px'
              }
            }
          }
        },
        [`#${this.IMG}box`]: {
          display: 'flex',
          'flex-flow': 'column',
          'justify-content': 'space-between',
          'align-items': 'center'
        },
        [`#${this.IMG}`]: {
          width: '200px',
          // 'min-height': `${200 * 1.38}px`,
          'border-radius': '8px',
          img: {
            'max-height': '288px'
          }
        },
        [`#${this.IMG}Landscape`]: {
          width: '120px'
          // 'min-height': `${120 * 1.38}px`
        },
        [`#${this.TEXT}`]: {
          'margin-top': '15px',
          'font-size': '16px'
        },
        [`#${this.BUTTON}`]: {
          width: '300px',
          height: '42px',
          'border-radius': '66px',
          'margin-top': '28px'
        },
        [`#${this.BUTTON} > svg`]: {
          width: '20px',
          height: '20px',
          'margin-right': '4px'
        },
        [`#${this.BUTTON} > span`]: {
          'font-size': '13px'
        },
        [`#${this.BRAND}`]: {
          'font-size': '12px',
          'margin-top': '47px'
        },
        [`#${this.DIVIDER} i`]: {
          width: '136px'
        },
        [`#${this.DIVIDER} span`]: {
          margin: '0 12px'
        },
        [`#${this.CARD}`]: {
          width: '342px',
          height: '80px',
          padding: '0 16px',
          'border-radius': '10px',
          'margin-top': '13px'
        },
        [`#${this.CARD} img`]: {
          width: '48px',
          height: '48px'
        },
        [`#${this.CARD} h2`]: {
          width: '253px',
          'font-size': '14px',
          'margin-bottom': '4px'
        },
        [`#${this.CARD} span`]: {
          width: '253px',
          'font-size': '12px'
        },
        [`#${this.POP}`]: {
          [`.${this.POP}Content`]: {
            margin: '24px 24px 0',
            'justify-content': 'space-between',
            'grid-template-columns': 'repeat(4, 64px)'
          }
        }
      };
      var sheet = document.styleSheets.item(document.styleSheets.length - 1);
      sheet.insertRule(
        `@keyframes spin {from {transform: rotate(0deg);}to {transform: rotate(360deg);}}`
      );
      sheet.insertRule(
        `@keyframes glint {0% {background: #0076FE}50% {background: #3590FB}100% {background: #0076FE}}`
      );
      for (var key in css) {
        var style = _get(css, key, null);
        if (style) {
          updateStyleSheet(key, style, '', sheet);
        }
      }
      for (var key in mobile_css) {
        var style = _get(mobile_css, key, null);
        if (style) {
          updateStyleSheet(
            key,
            style,
            // '@media screen and (max-device-width: 480px) and (orientation: portrait), (max-device-height: 480px) and (orientation: landscape)',
            '@media screen and (max-device-width: 624px), (max-device-height: 624px)',
            sheet
          );
        }
      }

      if (isPhone() || this.isMobile) {
        // document.body.style.transform = 'translateX(100%)';
        document.body.id = 'phoneNeatTemplate';
        updateStyleSheet('#phoneNeatTemplate', {
          transform: this.upsideOnMobile
            ? `translate(${g.innerWidth}px, ${g.innerHeight}px) rotate(90deg) !important`
            : `translate(${g.innerWidth}px, 0) !important`,
          'transform-origin': '0 0',
          overflow: 'unset !important'
        });
        updateStyleSheet(`#${this.BODY}`, {
          height: `${this.upsideOnMobile ? g.innerWidth : g.innerHeight}px`,
          width: `${this.upsideOnMobile ? g.innerHeight : g.innerWidth}px`
        });
      }

      if (
        location.search.toLowerCase().indexOf('phone=true') >= 0 ||
        location.search.toLowerCase().indexOf('phonebook=true') >= 0
      ) {
        updateStyleSheet(`.${this.POP}Content`, {
          'grid-template-columns': 'repeat(4, 64px) !important'
        });
      }
    },
    initHtml() {
      var $body = document.createElement('div');
      var shot_img = getMeta();
      if (!shot_img || shot_img === '' || /localhost|192\.168/.test(shot_img))
        shot_img = _get(g, 'htmlConfig.fliphtml5_pages.0.t', '');
      $body.id = this.BODY;
      $body.insertAdjacentHTML(
        'beforeend',
        `<div id="${this.CONTENT}">
          <div id="${this.IMG}box">
            <div id="${this.IMG}" >
              <img src="${shot_img}?${Date.now()}" />
            </div>
            <div id="${this.BRAND}Landscape"></div>
          </div>
          <div id="${this.TEXT}">${_get(this.config, 'loadingCaption', document.title)}</div>
					<div id="${this.BUTTON}"><span>${_get(g, 'htmlConfig.language.0.btnTitleText', '')}</span></div>
				</div>`
      );
      document.body.insertAdjacentElement('beforeend', $body);

      // if (brandVisible)
      //   document.querySelector(`#${this.CONTENT}`).appendChild(this.createBrand());
      this.initBrand();

      try {
        if (document.getElementById('bacgradient_image'))
          document.getElementById('bacgradient_image').style.visibility = 'visible';
      } catch (error) {}
    },
    initBrand() {
      const timer = setInterval(async () => {
        if (!$) return;
        clearInterval(timer);

        var brandVisible = _get(g, 'htmlConfig.loadingConfig.loadingBrandInfoVisible', undefined);
        if (!brandVisible) return;
        let loadingBrandInfoUrl = _get(g, 'htmlConfig.loadingConfig.loadingBrandInfoUrl', '');
        if (loadingBrandInfoUrl === '') return;
        loadingBrandInfoUrl = changeBookResourceBaseURL(loadingBrandInfoUrl);
        // const host = /192\.168|127\.0|localhost/.test(location.host)
        //   ? 'https://test2.yunzhan365.com'
        //   : '';
        // const baseUrl = `${loadingBrandInfoUrl.replace(/\/config.json|\/default.json/, '')}`;
        const baseUrl = loadingBrandInfoUrl.split(/\/config.json|\/default.json/)[0];
        const data = await new Promise(resolve => {
          $.ajax({
            url: `${loadingBrandInfoUrl}`,
            type: 'get',
            dataType: 'json',
            success: data => resolve(data),
            error: () => resolve(null)
          });
        });

        if (!data) return;
        data.baseUrl = baseUrl;
        g.loadingConfig['loadingBrandInfo'] = data;
        var $brand = document.createElement('div');
        var logo = _get(
          data,
          'logo',
          'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADwAAAA9CAYAAADxoArXAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAcpSURBVHgB3VtNbBtFFB6H/vCjpg6tEOoPbMShvcU9cOCEywGOSSSOrWJfU4kkp1YqUpwDUjnF6aHXOOq1KMkRJBrnxKGHpBIHOGUDJEJAiZtQ/gqE972dt35eb2yvfxKvv2iya+/seL59b968eW82YQ4J+/v7STqgOIFLLpVSIpEomUNAwnQAllyayrtUUrYk69wGwuu2rFIpduIhtI2wJTlCZcx4ZCtQ2vuPi7v1vOJ75/xx45w7dlCzRSoFIr5g2oSWCVuik1QmjJJi8dEfXNa/+YvK32Zz+5+a7SRP9ZmhyydM+u2X/KLgGo/8DJF3TQtomnAYURBcevjMLCztsTRbgXPuOJF+0YyNnAqSn6GSb1bdmyJMZNN0mDfWAIFo7t6vZvXRn6YTSJHkJ64lTYbIW7hUcs2oeiTCVqo540m140SDgNRX5s/RuPfHfN54at6wtBsmTGQdOqzgd6GuIDp3/6k5CkyStKdvDPC4N560rzY6thsiTGQxrSxScWBlr2a3jVvHCHUaAWm7VEaJ9Hq9+/rqVbBkWbJQ4Ssf/nDkZAF3+zn15Xs2ksazJSu2rzVRk7BS42RheY8l26r1bSfQl9GPfjQL1DfjzRR1SR9IOEg2e/sn063IUN8U6UXb91AcOIbppg06OHAcoMZxAMa0nbPXaTxfCasTKmEimzPWQEFl4gL01d1i+5IiDrNhdaokbNUB0jWD7292hYGKgtTlk2btwQX5iOmqqK+HSRjjlufZuJEFMATRd4sqKVdImKSbocM8VHnwg+9MXAGHZO3BRZmjp0jKebkWlPA0/uXu7Zg4A9NV9mN/Vpm2LjHDJ2yly4bKmvhYQ5anxpuqxuR7LeGekK5Gvuzrj8gJj2HrnazFfeyGYeerQVlksMUWCbPIrQr0FApLu3I6jH9CmEVe6IGxG8Tyw9/llDkmxNGAZRt4Z8P0IpRaO/jPq4teVGeB4pYGYcSO2UOJgo0v3uQycf101TU48HDk8WT3v36LXb3c+KsVdfDEZ2+e4TZQB3VRz0qCgXvkOopaHESCIpyCK8ISjkpYYsm6g/z9eS8SoQH/FmWf/mbstLf22cWKeDTaQZ3TdOQQEpGdHh+oaAdkU3dP8sLfLhIawma5Lqs0eyGbbfKbdSclbCtBg8nrSSaG6KOQxTU8bNQVEqgj7eA6LK1ICdcmrp02UaDWBCxhB2c7u+2JZKQunfB+xMa+AHQeEkNn4d8Ov/eKX3/yzi9Vnp1WW5Cd+vQJn4vxGaH75btGUNr71z8H4bZKGGoJ6JWWbnvo0skKVQZZrRWQckItabQxhSbgYST7XzBRoNTfOWYOAcHxFuywNmggqCWuY2jyEIN2Iwqav/OQoMnVSLo1DLRWCjbcCsKkgCSZAOOptFseUzyuKbKy9OWzch1lT5KnqtU3auRU9wWPDISTMh1EBRuY8XJHYKwgCYzlSZqjoc6wzoLHlEmE2spYn711hpJvv1EHyp1a/7Y8RaIN2AB0WoxZ1Ck02e+37Qphtp7NGC6d2gRZzLPyefbm2Yq66Cg0AP6tPITMcD8XDTwkPBSed+nBBOf1wlI0nx9ZCmka1NcDX7YELEDy96tzWzoCKkm4Wsje/jnUuUD7UQMUakiVIGEmjJTkwnLDbZh0dqvqu6d2SGCOhMMBr4t/hcYsMox6yEAToMpDl4/7VhsPBSrP59tYm2/SnP1yxfVmMpXK2BUTNte7EqeAe1TAR7c2I52wAS6sC5NYHnZT7qgdgJZtfP4Gn1PEI9Fnk8ms1mPlDHvPANsmLJbwr09/GFE+bq9gbNgXInOUIJ6v1jAUUZZe3QytzoQBaDNL2Kp1AefqicQealFSkH0g/rpErDWMFpJocTdeAekOyh4Q3+eyWbYiL75vDJi4IyBdVz4Ek2mOsalSLN7jGtg7SLpAxRLJXsjhHAG2dq2gDhvK984FtzOFMZqj4sIziaNqI5hgXUmXyM4Er1cRttZsFOfYABYnq40VmBq7V8PqhOqs3eA1hfP8rbP+2rWbgT5iGFrkDtqZd+AgtVnzPMYxxkQ3kw6smXNhqiyou/WQLHeBDmOYl2G5o0YbOg0ECRbvvi4GFlNQtlb9umaYGsjQYcHbN3GBQy7dAoxZSLZRskCU3bTYIZDjlpcpOH7nyZF5YyAIa6zyWjXVWCPqfulJ422NSCIikeOoxeHmlKHC85+8JlMPZhSQnWv0/sg74vW+aXyGtBGu6fQKC0Qx5ag0TJFKNuo7EK288yDvOzj86+SGYsO43c7bNoQQjSxVjZbearHShopn5DuoOqdLKEDXjC8uaVME7zIj/dq9BVFMlXOtvM/UlveWLPG08cg7+hpIS0oUcW+t+iCDIDmSAMg6Sh45ANd4a/WWiAra/mYakceOAmwgSRubbG8CRSlEctW0ER15FU9gQ0dDxiPumOp3D0uquMYLJj5OdPA9xP8BgfFl858h6NEAAAAASUVORK5CYII='
        );
        if (!logo.includes('data:image')) logo = `${baseUrl}/${logo.split('./').pop()}`;

        var business = _get(data, 'business', []);

        $brand.id = this.BRAND;
        $brand.insertAdjacentHTML(
          'beforeend',
          `<div id="${this.DIVIDER}">
          <i></i>
            <span>${_get(g, 'htmlConfig.language.0.relatedInfoText', '')}</span>
          <i></i>
        </div>

        <div id="${this.CARD}">
          <img src="${logo}" />
          <div>
            <h2>${_get(
              data,
              'title',
              _get(htmlConfig, 'language.0.brandTitleUndefined', 'No brand name is set yet')
            )}</h2>
            ${business
              .map(function (o) {
                return `<span>${o.name}: ${o.value}</span>`;
              })
              .join('')}
          </div>
				</div>`
        );

        $brand.addEventListener('click', e => {
          e.stopPropagation();
          e.stopImmediatePropagation();
          e.preventDefault();
          this.showPop();
        });
        document.querySelector(`#${this.CONTENT}`).appendChild($brand);

        var contactWay = _get(data, 'contactWay', []);
        if (contactWay.length > 0) {
          let timer = setInterval(() => {
            if (window.uiBaseURL && uiBaseURL !== '') {
              clearInterval(timer);
              this.createContactPop(contactWay);
            }
          }, 200);
        }
        const $thumb = document.querySelector(`#${this.IMG} img`);
        $thumb.addEventListener('error', () => {
          $thumb.style.setProperty('visibility', 'hidden');
          updateStyleSheet(`#${this.IMG}`, {
            height: '200px',
            background: '#F9F9F9'
          });
        });

        this.$brand = $brand;
        this.onResize();
      }, 600);
    },
    createContactPop(list) {
      var $pop = document.createElement('div');
      $pop.id = this.POP;
      var $items = [];
      for (var c of list) $items.push(this.createContactItem(c));
      $pop.insertAdjacentHTML(
        'beforeend',
        `<div class="${this.POP}Mask"></div>
        <div class="${this.POP}Contain">
          <section class="${this.POP}Way">
            <div class="${this.POP}Title">
              <i></i>
              <span>${_get(htmlConfig, 'language.0.brandContactFrTitle', 'Contact author')}</span>
              <i></i>
            </div>
            <div class="${this.POP}Content">${$items.join('')}</div>
            <span class="${this.POP}Cancel">${_get(
          htmlConfig,
          'language.0.lblCancel',
          'Cancel'
        )}</span>
          </section>
          <section class="${this.POP}Qrcode" >
            <div class="${this.POP}Title">
              <svg class="svg" version="1.1" id="图层_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 20 20" style="enable-background:new 0 0 20 20;" xml:space="preserve"><path class="st0" d="M19.5,4.8C19.5,4.9,19.5,4.9,19.5,4.8c0.6,0.7,0.6,1.7-0.1,2.3l-8.3,8.1c-0.6,0.6-1.7,0.6-2.3,0L0.5,7.1l0,0c-0.6-0.6-0.6-1.6,0-2.2c0.7-0.6,1.7-0.6,2.3,0l7.2,6.9l7.1-6.9C17.8,4.2,18.8,4.2,19.5,4.8L19.5,4.8z"/></svg>
            </div>
            <p>${_get(htmlConfig, 'language.0.frmShareQRcode', 'Qrcode')}</p>
            <div class="${this.POP}QrcodeImg">
              <img src="" />
            </div>
            <span class="${this.POP}Cancel">${_get(
          htmlConfig,
          'language.0.lblCancel',
          'Cancel'
        )}</span>
          </section>
        </div>`
      );

      document.querySelector(`#${this.BODY}`).appendChild($pop);
      this.initPopEvent();
    },
    createContactItem(item) {
      var $item = `<div class="${this.POP}Item" key="${item.type}">
          <div>
            <img src="${uiBaseURL}contactWay_${item.type}.png" />
          </div>
          <span>${item.title}</span>
        </div>`;
      return $item;
    },
    initEvent() {
      var $button = document.querySelector(`#${this.BUTTON}`);
      $button.addEventListener('click', e => {
        e.stopPropagation();
        e.stopImmediatePropagation();
        e.preventDefault();
        if (_get($button, 'disableTap', true)) return;
        this.hide();
      });

      if (isPhone() || isPad()) this.bindDragEvent();

      const $cover = document.getElementById(this.IMG);
      $cover.addEventListener('click', e => {
        e.stopPropagation();
        e.stopImmediatePropagation();
        e.preventDefault();

        this.hide();
      });

      const $text = document.getElementById(this.TEXT);
      $text.addEventListener('click', e => {
        e.stopPropagation();
        e.stopImmediatePropagation();
        e.preventDefault();
        this.hide();
      });
    },
    initResizeEvent() {
      window.addEventListener('resize', debounce(this.onResize.bind(this), 300));
    },
    onResize() {
      if (!this.isMobile && !isPhone()) return;
      if ((g.isPad && isPad()) || (g.isPC && isPC())) return;
      let type = this.isIOS ? '' : screen.orientation.type.split('-')[0]; // 横屏：landscape  竖屏：portrait
      if (this.isIOS) {
        switch (window.orientation) {
          case 0:
          case 180:
            type = 'portrait';
            break;
          case 90:
          case -90:
            type = 'landscape';
            break;
        }
      }
      // 预览模式下会识别为横屏，强制写死为竖屏
      if (this.isMobile === false && isPhone()) {
        type = 'portrait';
      }
      if (type === 'portrait' && this.upsideOnMobile) {
        type = 'landscape';
      }
      const $content = document.getElementById(`${this.CONTENT}`);
      switch (type) {
        case 'portrait':
          if (this.$brand) {
            $content.appendChild(this.$brand);
          }
          $content.classList.remove(`${this.CONTENT}Landscape`);
          if (g.innerWidth < 375) {
            const scale = g.innerWidth / 375;
            // $content.style.transform = `scale(${scale})`;
          }
          break;
        case 'landscape':
          if (this.$brand) {
            const brandLandsape = document.getElementById(`${this.BRAND}Landscape`);
            brandLandsape.appendChild(this.$brand);
          }
          $content.classList.add(`${this.CONTENT}Landscape`);
          if (g.innerHeight < 375) {
            const scale = g.innerHeight / 375;
            // $content.style.transform = `scale(${scale})`;
          }
          break;
      }

      if (isPhone() || this.isMobile) {
        if (this.visible) {
          updateStyleSheet('#phoneNeatTemplate', {
            transform: this.upsideOnMobile
              ? `translate(${g.innerWidth}px, ${g.innerHeight}px) rotate(90deg) !important`
              : `translate(${g.innerWidth}px, 0) !important`
          });
        }
        updateStyleSheet(`#${this.BODY}`, {
          height: `${this.upsideOnMobile ? g.innerWidth : g.innerHeight}px`,
          width: `${this.upsideOnMobile ? g.innerHeight : g.innerWidth}px`
        });
      }
    },
    initPopEvent() {
      // 蒙层点击
      document.querySelector(`.${this.POP}Mask`).addEventListener('click', e => {
        e.stopPropagation();
        e.stopImmediatePropagation();
        e.preventDefault();
        this.hidePop();
      });

      // 取消按钮点击
      document.querySelectorAll(`.${this.POP}Cancel`).forEach(el =>
        el.addEventListener('click', e => {
          e.stopPropagation();
          e.stopImmediatePropagation();
          e.preventDefault();
          this.hidePop();
        })
      );

      // 点击联系方式
      document.querySelector(`.${this.POP}Content`).addEventListener('click', e => {
        e.stopPropagation();
        e.stopImmediatePropagation();
        e.preventDefault();

        const el = e.target;
        const key = el.getAttribute('key');
        if (!key) return;
        const contactWay = _get(g, 'loadingConfig.loadingBrandInfo.contactWay', []);
        const way = contactWay.find(o => o.type === key);
        switch (key) {
          // 二维码
          case 'qrcode':
          case 'wechatOfficialAccount':
            this.showPopQrcode(way);
            break;
          // 复制
          case 'wechat':
          case 'phone':
          case 'douyin':
          case 'weibo':
          case 'email':
          case 'address':
          default:
            this.copyText(way);
            break;
        }
      });

      // 点击返回按钮
      document.querySelector(`.${this.POP}Title svg`).addEventListener('click', e => {
        e.stopPropagation();
        e.stopImmediatePropagation();
        e.preventDefault();
        this.hidePopQrcode();
      });
    },
    bindDragEvent() {
      var that = this;
      var $body = document.querySelector(`#${that.BODY}`);
      // var startX = 0;
      var isDragging = false;
      // var lock = false;
      var $parent = $(document.body);

      const hammer = new Hammer.Manager($body);
      hammer.add(new Hammer.Pan({ threshold: 20 }));
      hammer.on('panstart panmove panend pancancel', e => {
        if (!e || !e.pointers || e.pointers.length != 1) return;
        if (e.type === 'panstart') {
          // 记录起始坐标
          // startX = this.upsideOnMobile ? e.pointers[0].clientY : e.pointers[0].clientX;

          updateStyleSheet('#phoneNeatTemplate', {
            transition: 'none',
            transform: this.upsideOnMobile
              ? `translate(${g.innerWidth}px, ${g.innerHeight}px) rotate(90deg) !important`
              : `translate(${g.innerWidth}px, 0) !important`
          });
        }

        // 计算水平和垂直方向上的移动距离
        var deltaX = this.upsideOnMobile ? e.deltaY : e.deltaX;
        if (Math.abs(deltaX) > 10) {
          isDragging = true;
        }
        if (!isDragging) return;
        // if (lock) return;
        if (deltaX > 0) {
          deltaX = 0;
        } else if (deltaX < (this.upsideOnMobile ? g.innerHeight : g.innerWidth) * -1) {
          deltaX = (this.upsideOnMobile ? g.innerHeight : g.innerWidth) * -1 + 2;
        }
        updateStyleSheet('#phoneNeatTemplate', {
          transform: this.upsideOnMobile
            ? `translate(${g.innerWidth}px, ${g.innerHeight + deltaX}px) rotate(90deg) !important`
            : `translate(${g.innerWidth + deltaX}px, 0) !important`
        });

        // lock = true;
        // setTimeout(function () {
        //   lock = false;
        // }, 20);

        if (e.type === 'panend' || e.type === 'pancancel') {
          if (!isDragging) return;

          // 如果水平方向上的移动距离大于20%像素，则隐藏
          var flag = Math.abs(deltaX) > (this.upsideOnMobile ? g.innerHeight : g.innerWidth) * 0.2;
          if (flag && deltaX < 0) {
            that.hide();
          } else that.show();
          isDragging = false;
        }
      });
    },
    setLoading(status) {
      var $button = document.querySelector(`#${this.BUTTON}`);
      if (status) {
        $button.innerHTML = `<svg t="1683698016306" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1506" width="20" height="20"><path d="M128 512c0-211.2 172.8-384 384-384s384 172.8 384 384c0 38.4-25.6 64-64 64s-64-25.6-64-64c0-140.8-115.2-256-256-256S256 371.2 256 512s115.2 256 256 256c38.4 0 64 25.6 64 64s-25.6 64-64 64C300.8 896 128 723.2 128 512z" p-id="1507" fill="#ffffff"></path></svg><span>${_get(
          g,
          'htmlConfig.language.0.btnLoadingText',
          ''
        )}</span>`;
        $button.disableTap = true;
        updateStyleSheet(`#${this.BUTTON}`, {
          animation: 'none'
        });
      } else {
        $button.innerHTML = `<span>${_get(g, 'htmlConfig.language.0.btnTitleText', '')}</span>`;
        $button.disableTap = false;
        updateStyleSheet(`#${this.BUTTON}`, {
          animation: 'glint 1.2s linear infinite'
        });
      }
    },
    copyText(way) {
      var $input = document.createElement('input');
      var text = way.copyText;
      $input.setAttribute('type', 'text');
      $input.setAttribute('value', text);
      $input.setAttribute('readonly', 'readonly');
      $input.style.cssText = `opacity: 0;position: absolute;`;
      document.body.appendChild($input);
      $input.select();
      $input.setSelectionRange(0, 99999);
      try {
        document.execCommand('copy');
        this.tipbox(way.tipText);
      } catch {
        this.tipbox('复制失败');
      } finally {
        // $input.remove();
      }
    },
    showPop() {
      updateStyleSheet(`#${this.POP}`, {
        display: 'block'
      });
      setTimeout(() => {
        updateStyleSheet(`.${this.POP}Contain`, {
          transform: 'translateY(0) !important'
        });
      }, 16);
    },
    hidePop() {
      updateStyleSheet(`.${this.POP}Contain`, {
        transform: `unset`
      });
      setTimeout(() => {
        updateStyleSheet(`#${this.POP}`, {
          display: 'none'
        });
        this.hidePopQrcode();
      }, 300);
    },
    showPopQrcode(way) {
      var brandInfo = _get(g, 'loadingConfig.loadingBrandInfo', {});
      var qrcode = way.qrcode;
      updateStyleSheet(`.${this.POP}Qrcode`, {
        display: 'block !important'
      });
      updateStyleSheet(`.${this.POP}Way`, { display: 'none' });
      document
        .querySelector(`.${this.POP}QrcodeImg img`)
        .setAttribute('src', `${brandInfo.baseUrl}/${qrcode.split('./').pop()}`);
    },
    hidePopQrcode() {
      updateStyleSheet(`.${this.POP}Qrcode`, { display: 'none' });
      updateStyleSheet(`.${this.POP}Way`, { display: 'unset' });
    },
    tipbox(message, d = 2000) {
      var $parent = document.getElementById(this.BODY);
      var $tips = document.createElement('div');
      var className = 'loading-contact-tips';
      var tipsCount = document.querySelectorAll(`.${className}`).length;
      $tips.className = className;
      $tips.innerText = message;
      $tips.style.cssText = `position: fixed;
        opacity: 1;
        padding: 10px 16px;
        background-color: #333;
        width: 190px;
        top: ${80 + tipsCount * 50}px;
        right: 0;
        left: 0;
        margin: auto;
        z-index: 99999;
        font-size: 15px;
        color: #f0f0f0;
        vertical-align: middle;
        text-align: center;
        box-shadow: 0 0 15px rgba(40,40,40,0.6);
        transition: opacity 0.3s;
        border-radius: 3px;`;
      $parent.appendChild($tips);
      setTimeout(() => {
        $tips.style.opacity = '1';
        setTimeout(() => {
          $tips.style.opacity = '0';
          setTimeout(() => $tips.remove(), 300);
        }, d);
      }, 16);
    },
    show() {
      this.visible = true;
      updateStyleSheet('#phoneNeatTemplate', {
        overflow: 'unset !important'
      });
      setTimeout(() => {
        if (isPhone() || this.isMobile) {
          updateStyleSheet('#phoneNeatTemplate', {
            transition: 'transform 0.3s',
            transform: this.upsideOnMobile
              ? `translate(${g.innerWidth}px, ${g.innerHeight}px) rotate(90deg) !important`
              : `translate(${g.innerWidth}px, 0) !important`
          });
        } else {
          updateStyleSheet(`#${this.BODY}`, {
            opacity: '1',
            transition: 'opacity 0.3s',
            'pointer-events': 'auto'
          });
        }
      }, 16);
    },
    hide() {
      if (isPhone() || this.isMobile) {
        updateStyleSheet('#phoneNeatTemplate', {
          transition: 'transform 0.3s',
          transform: 'unset'
        });
      } else {
        updateStyleSheet(`#${this.BODY}`, {
          opacity: '0',
          transition: 'opacity 0.3s',
          'pointer-events': 'none'
        });
      }

      setTimeout(
        function () {
          this.visible = false;
          updateStyleSheet('#phoneNeatTemplate', {
            overflow: 'unset'
          });
        }.bind(this),
        500
      );
    },
    changeLanguage(l) {
      const language = _get(g, 'htmlConfig.language', []).find(o => o.language === l);
      const $divider = document.querySelector(`#${this.DIVIDER} span`);
      const $button = document.querySelector(`#${this.BUTTON} span`);
      if ($divider) $divider.innerText = _get(language, 'relatedInfoText', '');
      if ($button) $button.innerText = _get(language, 'btnTitleText', '');
    },
    destroy() {
      this.setLoading(false);
      this.initEvent();
      this.initResizeEvent();
    }
  };

  function mitt(all) {
    all = all || new Map();
    return {
      all,
      on(type, handler) {
        const handlers = all.get(type);
        const added = handlers && handlers.push(handler);
        if (!added) {
          all.set(type, [handler]);
        }
      },
      off(type, handler) {
        const handlers = all.get(type);
        if (handlers) {
          handlers.splice(handlers.indexOf(handler) >>> 0, 1);
        }
      },
      emit(type, evt) {
        (all.get(type) || []).slice().map(handler => {
          handler(evt);
        });
        (all.get('*') || []).slice().map(handler => {
          handler(type, evt);
        });
      }
    };
  }
  // 创建一个全局的事件总线实例
  window.eventEmitter = mitt();
  // yunzhan
  function isBase64(str) {
    // 验证base64编码格式的正则表达式
    const base64Regex = /^(?:[A-Za-z0-9+/]{4})*?(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/;

    return base64Regex.test(str);
  }

  function ajax() {
    function createxmlHttpRequest() {
      if (window.ActiveXObject) {
        return new ActiveXObject('Microsoft.XMLHTTP');
      } else if (window.XMLHttpRequest) {
        return new XMLHttpRequest();
      }
    }
    let ajaxData = {
      type: (arguments[0].type || 'GET').toUpperCase(),
      url: arguments[0].url || '',
      async: arguments[0].async || 'true',
      data: arguments[0].data || null,
      dataType: arguments[0].dataType || 'json',
      contentType: arguments[0].contentType || 'application/x-www-form-urlencoded; charset=utf-8',
      beforeSend: arguments[0].beforeSend || function () {},
      success: arguments[0].success || function () {},
      error: arguments[0].error || function () {}
    };
    ajaxData.beforeSend();
    let xhr = createxmlHttpRequest();
    xhr.open(ajaxData.type, ajaxData.url, ajaxData.async);
    xhr.setRequestHeader('Content-Type', ajaxData.contentType);
    xhr.send();
    xhr.onreadystatechange = function () {
      if (xhr.readyState == 4) {
        if (xhr.status == 200) {
          ajaxData.success(xhr.response);
        } else {
          ajaxData.error();
        }
      }
    };
  }

  function getUserInfo() {
    const domainRegex = /yunzhan365\.com|sharedbook\.cn|huaceshu\.cn/;
    const booksPathRegex = /^https?:\/\/[^/]+\/books(?:\/|$)/;
    const fh5DomailRegex = /fliphtml5.com|flipbuilder/;
    try {
      const url = window.location.href;
      const domain = window.location.hostname;
      if (url.indexOf('templates') > -1) return;
      // 检查条件
      if (
        domainRegex.test(domain) ||
        fh5DomailRegex.test(domain) ||
        (!domainRegex.test(domain) && booksPathRegex.test(url))
      ) {
        ajax({
          url: fh5DomailRegex.test(domain) ? '../getui.json' : '../../getui.json',
          success: msg => {
            if (!msg) return;
            if (!isBase64(msg)) return;
            const data = JSON.parse(atob(msg));
            window.userInfo = data;
            window.eventEmitter.emit('loadUserInfo', data);
          },
          error: err => {
            console.log('getui', err);
          }
        });
      }
    } catch (e) {
      console.error(`URL 解析错误: ${url}`);
    }
  }

  getUserInfo();

  /**
   * 拦截页面内所有 <a> 标签的点击跳转事件
   * 如果在微信小程序环境下，阻止默认跳转行为。
   *
   * @param {boolean} disable - 是否禁用 a 标签跳转。为 true 时禁用。
   */
  function interceptALinks(disable) {
    if (!disable) return;

    /**
     * 点击事件处理函数
     * @param {Event} event - DOM 事件对象
     */
    const handleLinkClick = event => {
      // 查找事件源是否为 <a> 标签或其子节点
      let target = event.target;
      while (target && target !== document) {
        if (target.tagName && target.tagName.toLowerCase() === 'a') {
          event.preventDefault();
          // 如有需要可添加提示信息或日志记录
          console.warn('当前环境为微信小程序，已禁用链接跳转。');
          break;
        }
        target = target.parentNode;
      }
    };

    document.addEventListener('click', handleLinkClick, true);
  }

  /**
   * 重写 window.open 方法，在微信小程序环境下禁止打开新窗口
   * 如果需要保留原始逻辑，可通过备份进行扩展。
   *
   * @param {boolean} disable - 是否禁用 window.open 调用。为 true 时禁用。
   */
  function overrideWindowOpen(disable) {
    if (!disable || typeof window === 'undefined') return;

    // 备份原始 window.open 方法
    const originalWindowOpen = window.open;

    /**
     * 重写后的 window.open 方法
     * @param {...any} args - 原始 window.open 的所有参数
     * @returns {null} 禁用打开窗口，返回 null
     */
    window.open = function (...args) {
      console.warn('当前环境为微信小程序，已禁用 window.open 调用。');
      return null;
    };

    // 如果需要，在未来可以通过条件重新启用 window.open，或提供 fallback 逻辑
    // window.restoreWindowOpen = () => { window.open = originalWindowOpen; }
  }

  /**
   * 初始化微信小程序环境下的导航禁用功能
   * 如果当前为微信小程序环境，则拦截 a 标签点击和重写 window.open 方法
   */
  function disableNavigationInMiniProgram() {
    const ua = window.navigator.userAgent.toLowerCase();
    if (ua.match(/MicroMessenger/i) == 'micromessenger') {
      try {
        wx.miniProgram.getEnv(function (res) {
          if (res.miniprogram) {
            interceptALinks(true);
            overrideWindowOpen(true);
          }
        });
      } catch (error) {}
    }
  }

  disableNavigationInMiniProgram();

  switch (_get(g, 'htmlConfig.loadingConfig.loadingType', '0')) {
    case 0:
    case '0':
      jsLoadingBar = new LoadingJS();
      break;
    case 1:
    case '1':
      jsLoadingBar = new GuideLoadingJS();
      // if (!htmlConfig.loadingConfig.loadingBrandInfoUrl) jsLoadingBar = new GuideLoadingJS();
      // else {
      //   var xhr = new XMLHttpRequest();
      //   xhr.overrideMimeType('application/json');
      //   xhr.open(
      //     'GET',
      //     `//${window.location.hostname}${htmlConfig.loadingConfig.loadingBrandInfoUrl}`,
      //     true
      //   );
      //   xhr.onload = function () {
      //     if (xhr.status >= 200 && xhr.status < 300) {
      //       var jsonData = JSON.parse(xhr.responseText);
      //       // 在这里使用jsonData
      //       htmlConfig.loadingBrandConfig = jsonData;
      //       jsLoadingBar = new GuideLoadingJS();
      //     } else {
      //       jsLoadingBar = new GuideLoadingJS();
      //     }
      //   };
      //   xhr.send(null);
      // }
      break;
  }
})(window);
