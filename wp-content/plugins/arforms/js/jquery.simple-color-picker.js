var $=jQuery;$.fn.simpleColorPicker=function(e){var f={colorsPerLine:8,colors:["#000000","#444444","#666666","#999999","#cccccc","#eeeeee","#f3f3f3","#ffffff","#ff0000","#ff9900","#ffff00","#00ff00","#00ffff","#0000ff","#9900ff","#ff00ff","#f4cccc","#fce5cd","#fff2cc","#d9ead3","#d0e0e3","#cfe2f3","#d9d2e9","#ead1dc","#ea9999","#f9cb9c","#ffe599","#b6d7a8","#a2c4c9","#9fc5e8","#b4a7d6","#d5a6bd","#e06666","#f6b26b","#ffd966","#93c47d","#76a5af","#6fa8dc","#8e7cc3","#c27ba0","#cc0000","#e69138","#f1c232","#6aa84f","#45818e","#3d85c6","#674ea7","#a64d79","#990000","#b45f06","#bf9000","#38761d","#134f5c","#0b5394","#351c75","#741b47","#660000","#783f04","#7f6000","#274e13","#0c343d","#073763","#20124d","#4C1130"],showEffect:"",hideEffect:"",onChangeColor:!1};void 0==$&&($=jQuery);var o=$.extend(f,e);return this.each(function(){function e(e){"fade"==o.hideEffect?e.fadeOut():"slide"==o.hideEffect?e.slideUp():e.hide()}function c(e){"fade"==o.showEffect?e.fadeIn():"slide"==o.showEffect?e.slideDown():e.show()}for(var i=$(this),r="",t=i.attr("id").replace(/-/g,"")+"_",l=0;l<o.colors.length;l++){var d=o.colors[l],a="";l%o.colorsPerLine==0&&(a="clear: both; "),l>0&&a&&$.browser&&$.browser.msie&&$.browser.version<=7&&(a="",r+='<li style="float: none; clear: both; overflow: hidden; background-color: #fff; display: block; height: 1px; line-height: 1px; font-size: 1px; margin-bottom: -2px;"></li>'),r+='<li id="'+t+"color-"+l+'" class="color-box" style="'+a+"background-color: "+d+'" title="'+d+'"></li>'}var s=$('<div id="'+t+'color-picker" class="color-picker" style="position: absolute; left: 0px; top: 0px;"><div class="arf-color-picker-heading">SOLID COLORS</div><div style="clear: both;"></div><ul>'+r+'</ul><div style="clear: both;"></div></div>');$("body").append(s),s.hide(),s.find("li.color-box").click(function(){i.is("input")&&(i.val(o.colors[this.id.substr(this.id.indexOf("-")+1)]),i.blur()),$.isFunction(f.onChangeColor)&&f.onChangeColor.call(i,o.colors[this.id.substr(this.id.indexOf("-")+1)]),e(s)}),$("body").on("click",function(){e(s)}),s.click(function(e){e.stopPropagation()});var n=function(e){var f=i.offset(),o=f.left;o<f.left&&(o=f.left),e.css({left:o,top:f.top+i.outerHeight()}),c(e)};i.click(function(e){return"arf_disable"==jQuery(this).parents(".arf_field_type_colorpicker").attr("data-view")?!1:(e.stopPropagation(),void(i.is("input")||n(s)))}),i.focus(function(){return jQuery(this).hasClass("arf_editor_basic_colorpicker")?void n(s):!1})})};