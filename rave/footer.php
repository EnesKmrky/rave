<?php
// footer.php dosyası (GÜNCELLENMİŞ HALİ)
?>
    </main>

    <footer class="footer">
        <p>&copy; <?php echo date("Y"); ?> RP ŞEHİR. Tüm hakları saklıdır gardaşım.</p>
    </footer>

    <script>
        // Elemanları bi yakalayalım
        const lightbox = document.getElementById('myLightbox');
        const lightboxImg = document.getElementById('lightboxImg');

        // Resim açma fonksiyonu
        function openLightbox(element) {
            lightbox.style.display = "flex"; // flex yapınca ortalıyor
            lightboxImg.src = element.src;
        }

        // Resim kapama fonksiyonu
        function closeLightbox() {
            lightbox.style.display = "none";
        }
    </script>
</body>
</html>