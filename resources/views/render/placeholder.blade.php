{{-- Shown while an item has neither render data nor a preview image. --}}
<div class="render-viewer__placeholder" @if ($hidden) hidden @endif>
    <i class="ri-time-line render-viewer__placeholder-icon"></i>
    <div class="render-viewer__loader-text">Render is being prepared</div>
    <div class="render-viewer__placeholder-note">Check back after the next map update.</div>
</div>
