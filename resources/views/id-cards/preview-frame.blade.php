{{--
    The template preview's page, shown in an iframe inside the Template
    window so the sample card's colours never clash with the ID Cards
    page's own card styles. $card, $shared and $design from
    IdCardService::sample(). Front and back side by side, scaled to fit.
--}}
@php($orientation = $design['orientation'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        html, body { margin: 0; padding: 0; background: transparent; font-family: Arial, Helvetica, sans-serif; }
        .stage { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px; padding: 6px 4px 8px; }
        .side { text-align: center; }
        .side-label { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        .idc { box-shadow: 0 2px 8px rgba(16, 24, 40, .16); }
        @include('id-cards._card-css')
    </style>
</head>
<body>
    <div class="stage" id="stage">
        <div class="side">
            <div class="side-label">Front</div>
            @include('id-cards.templates.'.$orientation.'-front')
        </div>
        <div class="side">
            <div class="side-label">Back</div>
            @include('id-cards.templates.'.$orientation.'-back')
        </div>
    </div>
    <script>
        // Fit front and back side by side (or stacked on a narrow window),
        // then size the iframe to its content.
        (function () {
            var stage = document.getElementById('stage');
            function fit() {
                stage.style.zoom = 1;
                var card = stage.querySelector('.idc');
                var cardWidth = card.offsetWidth;
                var width = document.documentElement.clientWidth - 28;
                var sideBySide = (width - 14) / (2 * cardWidth);
                var scale = sideBySide >= 0.8 ? Math.min(sideBySide, 1.35) : Math.min(width / cardWidth, 1.35);
                stage.style.zoom = scale;
                if (window.frameElement) {
                    // Collapse first so the frame can shrink as well as grow.
                    window.frameElement.style.height = '1px';
                    window.frameElement.style.height = document.body.scrollHeight + 'px';
                }
            }
            // Resizing the frame's height fires resize too: refit only when
            // the width really changed, or it would loop.
            var lastWidth = document.documentElement.clientWidth;
            fit();
            window.addEventListener('resize', function () {
                var width = document.documentElement.clientWidth;
                if (width !== lastWidth) {
                    lastWidth = width;
                    fit();
                }
            });
        })();
    </script>
</body>
</html>
