<?php include 'header.php'; ?>

<style>
    /* Sadece bu sayfaya özel küçük bir stil ekleyelim */
    .update-post {
        background: #161b22;
        border: 1px solid #30363d;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 25px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .update-post:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 170, 255, 0.1);
    }
    .update-post h3 {
        margin-top: 0;
        color: #00aaff;
        font-size: 1.8em;
    }
    .update-post .date {
        font-size: 0.9em;
        color: #8b949e;
        margin-bottom: 15px;
        border-bottom: 1px solid #30363d;
        padding-bottom: 10px;
    }
</style>

<div class="container">
    <h1>Sunucu Güncellemeleri</h1>
    
    <div class="update-post">
        <h3>Yeni Meslek: Kamyon Şoförlüğü</h3>
        <div class="date">28.06.2025 tarihinde yayınlandı</div>
        <p>Ekonomiyi canlandırmak ve yasal para kazanma yollarını artırmak için şehirdeki lojistik firmasıyla anlaştık! Artık ehliyeti olan herkes kamyon şoförlüğü yaparak para kazanabilir. Yeni Scania ve Volvo marka tırlar galeriye eklendi. Rotalar ve kazançlar hakkında detaylı bilgi için oyun içi firmayı ziyaret edin.</p>
    </div>

    <div class="update-post">
        <h3>Polis Departmanı Modernizasyonu</h3>
        <div class="date">25.06.2025 tarihinde yayınlandı</div>
        <p>Los Santos Polis Departmanı (LSPD), devletten aldığı yeni bütçe ile ekipmanlarını modernize etti. Yeni zırhlı araçlar, gelişmiş telsiz sistemi ve yeni sorgulama yetkileri eklendi. Artık suçluların işi çok daha zor. Adaletten kaçış yok!</p>
    </div>

    <div class="update-post">
        <h3>Yeni Ev ve Dekorasyon Sistemi</h3>
        <div class="date">22.06.2025 tarihinde yayınlandı</div>
        <p>Emlak piyasası canlandı! Şehrin farklı bölgelerinde yeni apartmanlar ve villalar satışa sunuldu. Artık evinizi satın alabilir, içini zevkinize göre dekore edebilir ve envanterinizi güvenle saklayabilirsiniz. Emlakçıya uğrayıp hayalinizdeki evi bulun.</p>
    </div>

</div>

<?php include 'footer.php'; ?>