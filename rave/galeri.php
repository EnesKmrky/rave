<?php include 'header.php'; ?>

<style>
    /* Galeriye özel stiller */
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
    .gallery-item img {
        width: 100%;
        height: 220px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #30363d;
        cursor: pointer;
        transition: transform 0.3s, filter 0.3s;
    }
    .gallery-item img:hover {
        transform: scale(1.05);
        filter: brightness(1.2);
    }

    /* Resme tıklayınca açılan büyük ekran (Lightbox) */
    .lightbox {
        display: none; /* JS ile açılacak */
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        justify-content: center;
        align-items: center;
    }
    .lightbox-content {
        max-width: 80%;
        max-height: 80%;
        animation: fadeIn 0.5s;
    }
    .lightbox-close {
        position: absolute;
        top: 20px;
        right: 40px;
        color: #fff;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
    }
</style>

<div class="container">
    <h2>Galeriden Kareler</h2>
    <p style="text-align: center; margin-top: -30px; margin-bottom: 40px;">Sunucumuzda yaşanan unutulmaz anlardan bazıları...</p>

    <div class="gallery-grid">
        <div class="gallery-item"><img src="https://c.wallhere.com/photos/b0/a8/Grand_Theft_Auto_V_Rockstar_Games_Grand_Theft_Auto_Online_car_tuning_car_show_Screen_Shot-1823129.jpg!d" alt="Galeriden Kare 1" onclick="openLightbox(this)"></div>
        <div class="gallery-item"><img src="https://wallpapercave.com/wp/wp6843075.jpg" alt="Galeriden Kare 2" onclick="openLightbox(this)"></div>
        <div class="gallery-item"><img src="https://wallpapercave.com/wp/wp6843108.jpg" alt="Galeriden Kare 3" onclick="openLightbox(this)"></div>
        <div class="gallery-item"><img src="https://c4.wallpaperflare.com/wallpaper/705/193/165/gta-v-gta-5-franklin-car-wallpaper-preview.jpg" alt="Galeriden Kare 4" onclick="openLightbox(this)"></div>
        <div class="gallery-item"><img src="https://images.hdqwalls.com/wallpapers/gta-online-the-contract-4k-3p.jpg" alt="Galeriden Kare 5" onclick="openLightbox(this)"></div>
        <div class="gallery-item"><img src="https://www.gtabase.com/images/gta-5/screenshots/gtao-next-gen-10.jpg" alt="Galeriden Kare 6" onclick="openLightbox(this)"></div>
    </div>
</div>

<div id="myLightbox" class="lightbox">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img class="lightbox-content" id="lightboxImg">
</div>


<?php include 'footer.php'; ?>