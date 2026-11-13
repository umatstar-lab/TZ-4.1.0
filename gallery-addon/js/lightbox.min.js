(function ($) {
    'use strict';

    // CSS multicol can leave a requested column empty while balancing indivisible
    // cards. Split into contiguous groups to keep the original lightbox order.
    $(function () {
        $('.tz-portfolio-gallery .masonry-container').each(function () {
            var gallery = this;
            var images = Array.prototype.slice.call(gallery.children).filter(function (node) {
                return node.classList.contains('gallery-listing');
            });
            var previous = 0;
            var scheduled = false;
            function layout() {
                scheduled = false;
                var columns = parseInt(window.getComputedStyle(gallery).getPropertyValue('--tz-gallery-columns'), 10);
                columns = Math.min(10, Math.max(1, columns || 1));
                if (columns === previous || !images.length) { return; }
                previous = columns;
                var fragment = document.createDocumentFragment();
                var offset = 0;
                for (var i = 0; i < columns; i++) {
                    var column = document.createElement('div');
                    column.className = 'tz-gallery-column';
                    var count = Math.floor(images.length / columns) + (i < images.length % columns ? 1 : 0);
                    for (var n = 0; n < count; n++) { column.appendChild(images[offset++]); }
                    fragment.appendChild(column);
                }
                gallery.replaceChildren(fragment);
                gallery.classList.add('tz-responsive-masonry');
            }
            layout();
            window.addEventListener('resize', function () {
                if (!scheduled) { scheduled = true; window.requestAnimationFrame(layout); }
            });
        });
    });

    var lightboxOpen = false;

    $(document).on('click', '.tz_portfolio_gallery .gallery-title', function (event) {
        // Do not block the ordinary image link when Fancybox failed to load.
        // This prevents a completely dead click on partially migrated sites.
        if (!$.fancybox || typeof $.fancybox.open !== 'function') {
            return;
        }

        event.preventDefault();

        if (lightboxOpen) {
            return;
        }

        var $trigger = $(this);
        var $gallery = $trigger.closest('.tz_portfolio_gallery');
        var clickedId = String($trigger.attr('data-id') || '');
        var index = 0;
        var items = [];

        $gallery.find('.gallery-title').each(function () {
            var $link = $(this);
            var href = $link.attr('href');

            if (!href) {
                return;
            }

            var dataId = String($link.attr('data-id') || '');
            var caption = $link.closest('.gallery-listing').find('.image-caption').first().text().trim();

            items.push({
                src: href,
                opts: {
                    caption: caption,
                    thumb: $link.attr('data-thumb') || href
                }
            });

            if (dataId === clickedId) {
                index = items.length - 1;
            }
        });

        if (!items.length) {
            return;
        }

        var buttons = ['zoom', 'slideShow', 'fullScreen', 'thumbs', 'close'];
        var rawButtons = $gallery.closest('.tz-portfolio-gallery').attr('data-lightbox-buttons');

        if (rawButtons) {
            try {
                var parsedButtons = JSON.parse(rawButtons);
                if (Array.isArray(parsedButtons) && parsedButtons.length) {
                    buttons = parsedButtons;
                }
            } catch (error) {
                // Keep the safe defaults when custom configuration is invalid.
            }
        }

        $.fancybox.open(items, {
            loop: true,
            thumbs: {
                autoStart: $(window).width() >= 768
            },
            buttons: buttons,
            beforeShow: function () {
                lightboxOpen = true;
            },
            afterClose: function () {
                lightboxOpen = false;
            }
        }, index);
    });
}(jQuery));
