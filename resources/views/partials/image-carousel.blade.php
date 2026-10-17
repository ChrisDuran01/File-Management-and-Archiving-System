{{-- Stacked photo deck. Pass $images (collection of AnnouncementImage).
     Swipe/drag or tap the top photo to send it to the back; arrows go either way.
     The CSS/JS is output once per page and also exposes window.initPhotoStack(el)
     so the officer form can build a live preview with the same look. --}}
@once
<style>
    .ps { max-width: 340px; margin: 16px auto 12px; }
    .ps-stage { position: relative; aspect-ratio: 1 / 1; }
    .ps-slide {
        position: absolute; inset: 7%; border-radius: 18px; overflow: hidden; background: #222;
        border: 2px solid rgba(255,255,255,.92); box-shadow: 0 10px 26px rgba(0,0,0,.35);
        transition: transform .38s cubic-bezier(.2,.8,.2,1), opacity .3s; touch-action: pan-y; user-select: none;
        will-change: transform;
    }
    .ps-slide.front { cursor: grab; }
    .ps-slide.dragging { transition: none; cursor: grabbing; }
    .ps-slide img { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; -webkit-user-drag: none; }
    .ps-stage:focus-visible { outline: 3px solid #058028; outline-offset: 4px; border-radius: 18px; }
    /* 44px targets (Fitts's law / WCAG 2.5.5) so thumbs hit them first time. */
    .ps-nav { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 8px; }
    .ps-nav button { width: 44px; height: 44px; border: 0; border-radius: 50%; background: rgba(127,127,127,.2); color: inherit; font-size: 22px; line-height: 1; cursor: pointer; transition: background .15s; }
    .ps-nav button:hover { background: rgba(127,127,127,.36); }
    .ps-nav button:focus-visible { outline: 3px solid #058028; outline-offset: 2px; }
    .ps-count { font-size: .8rem; font-weight: 600; opacity: .8; min-width: 52px; text-align: center; font-variant-numeric: tabular-nums; }
    .ps-hint { text-align: center; font-size: .75rem; opacity: .7; margin-top: 2px; transition: opacity .3s; }
    .ps-hint.gone { opacity: 0; }
    .ps-x { position: absolute; top: 8px; left: 8px; width: 40px; height: 40px; border: 0; border-radius: 50%; background: rgba(0,0,0,.7); color: #fff; font-size: 22px; line-height: 1; cursor: pointer; display: none; z-index: 5; }
    .ps-x:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
    .ps-slide.front .ps-x { display: block; }
    @media (prefers-reduced-motion: reduce) { .ps-slide { transition: none; } }
</style>
<script>
    // Resting pose of the card at depth p (0 = top): rotation, x, y.
    const PS_POSE = [[-2, 0, 0], [5, 14, -6], [-6, -12, 8], [8, 20, 10], [-9, -18, -10]];

    window.initPhotoStack = function (el) {
        const stage = el.querySelector('.ps-stage');
        const slides = Array.from(stage.querySelectorAll('.ps-slide'));
        const count = el.querySelector('.ps-count');
        let order = slides.slice();
        let drag = null;

        function layout() {
            order.forEach(function (s, p) {
                const pose = PS_POSE[Math.min(p, PS_POSE.length - 1)];
                s.style.zIndex = order.length - p;
                s.style.opacity = p > 3 ? 0 : 1;
                s.style.transform = 'translate(' + pose[1] + 'px,' + pose[2] + 'px) rotate(' + pose[0] + 'deg)';
                s.classList.toggle('front', p === 0);
            });
            if (count) count.textContent = (slides.indexOf(order[0]) + 1) + ' / ' + slides.length;
        }

        function next(dir) {
            if (order.length < 2) return layout();
            const top = order[0];
            // Fly off the way it was thrown, then slip to the back of the pile.
            top.style.transform = 'translate(' + (dir * 130) + '%,0) rotate(' + (dir * 18) + 'deg)';
            top.style.opacity = 0;
            order.push(order.shift());
            setTimeout(layout, 180);
        }

        function prev() {
            if (order.length < 2) return;
            order.unshift(order.pop());
            layout();
        }

        stage.addEventListener('pointerdown', function (e) {
            const top = order[0];
            if (e.target.closest('.ps-x') || ! top.contains(e.target)) return;
            drag = { x: e.clientX, dx: 0 };
            top.classList.add('dragging');
            stage.setPointerCapture(e.pointerId);
        });
        stage.addEventListener('pointermove', function (e) {
            if (! drag) return;
            drag.dx = e.clientX - drag.x;
            order[0].style.transform = 'translate(' + drag.dx + 'px,0) rotate(' + (drag.dx / 14) + 'deg)';
        });
        function release() {
            if (! drag) return;
            const dx = drag.dx;
            order[0].classList.remove('dragging');
            drag = null;
            // A drag past 50px or a plain tap sends the top card away.
            if (Math.abs(dx) > 50) next(dx > 0 ? 1 : -1);
            else if (Math.abs(dx) < 6) next(1);
            else layout();
        }
        stage.addEventListener('pointerup', release);
        stage.addEventListener('pointercancel', release);

        // Once someone has used the deck, the "how" hint has done its job.
        const hint = el.querySelector('.ps-hint');
        const used = () => hint && hint.classList.add('gone');
        stage.addEventListener('pointerdown', used);

        el.querySelector('.ps-prev')?.addEventListener('click', () => { used(); prev(); });
        el.querySelector('.ps-next')?.addEventListener('click', () => { used(); next(1); });

        // Keyboard users get the same controls as touch users.
        stage.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); used(); next(1); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); used(); prev(); }
        });
        layout();
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ps[data-auto]').forEach(window.initPhotoStack);
    });
</script>
@endonce

@if($images->isNotEmpty())
<div class="ps" data-auto>
    <div class="ps-stage" @if($images->count() > 1) tabindex="0" role="group" aria-roledescription="photo stack" aria-label="{{ $images->count() }} photos{{ isset($title) ? ' for '.$title : '' }}. Use the left and right arrow keys to browse." @endif>
        @foreach($images as $img)
            <div class="ps-slide"><img src="{{ route('announcements.image', $img->id) }}" alt="Photo {{ $loop->iteration }} of {{ $images->count() }}{{ isset($title) ? ' for '.$title : '' }}" loading="lazy" draggable="false"></div>
        @endforeach
    </div>
    @if($images->count() > 1)
        <div class="ps-nav">
            <button type="button" class="ps-prev" aria-label="Previous photo">&#8249;</button>
            <span class="ps-count" aria-live="polite">1 / {{ $images->count() }}</span>
            <button type="button" class="ps-next" aria-label="Next photo">&#8250;</button>
        </div>
        <div class="ps-hint">Swipe or tap the photo to see more</div>
    @endif
</div>
@endif
