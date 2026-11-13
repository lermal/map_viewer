{{-- Parallax space background shared by all public pages; animated by resources/js/background.js. --}}
<div class="render-background" aria-hidden="true">
    <div class="render-background__layer render-background__layer--back"
        style="background-image: url('{{ asset('images/parallax--back.webp') }}');"></div>
    <div class="render-background__layer render-background__layer--front"
        style="background-image: url('{{ asset('images/parallax--front.webp') }}');"></div>
    <div class="render-background__layer render-background__layer--stars1"
        style="background-image: url('{{ asset('images/stars-1.webp') }}');"></div>
    <div class="render-background__layer render-background__layer--stars2"
        style="background-image: url('{{ asset('images/stars-2.webp') }}');"></div>
    <div class="render-background__layer render-background__layer--meteors1"
        style="background-image: url('{{ asset('images/parallax--meteors.webp') }}');"></div>
    <div class="render-background__layer render-background__layer--meteors2"
        style="background-image: url('{{ asset('images/parallax--meteors.webp') }}');"></div>
</div>
