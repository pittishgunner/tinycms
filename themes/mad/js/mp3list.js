// JavaScript Document
jQuery(document).ready(function($) {
// Setup the player to autoplay the next track
        var a = audiojs.createAll({
          trackEnded: function() {
            var next = $('ol li.playing').next();
            if (!next.length) next = $('ol li').first();
            next.addClass('playing').siblings().removeClass('playing');
            audio.load($('a', next).attr('data-src'));
            audio.play();
          }
        });
        
        // Load in the first track
        var audio = a[0];
            first = $('ol li').attr('data-src');
        $('ol li').first().addClass('playing');
        if (audio!=undefined) audio.load(first);

        // Load in a track on click
        $('ol li div.mli_l').click(function(e) {
          e.preventDefault();
          $(this).parents("li").addClass('playing').siblings().removeClass('playing');
          audio.load($(this).parents("li").attr('data-src'));
          audio.play();
        });
});