var easing,e,pos;$(function(){$(".go-top-sets li").on("click",function(){switch(easing=$(this).attr("class"),pos=$(window).scrollTop(),$("body").css({"margin-top":-pos+"px","overflow-y":"scroll"}),$(window).scrollTop(0),easing){case"easing-1":e="cubic-bezier(0.600, 0.040, 0.980, 0.335)";break;case"easing-2":e="cubic-bezier(1.000, -0.560, 0.000, 1.455)";break;case"easing-3":e="cubic-bezier(0.175, 0.885, 0.320, 1.275)"}$("body").css("transition","all 1s "+e),$("body").css("margin-top","0"),$("body").on("webkitTransitionEnd transitionend msTransitionEnd oTransitionEnd",function(){$("body").css("transition","none")})})});