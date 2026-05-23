# 🛡️ Google Ads Maliyet ve Sahte Tıklama Engelleyici

Bu proje, Google Ads hizmetinden faydalanan web sitelerinin sahte tıklamalar yoluyla bütçe tüketmesini ve mali zarara uğramasını engellemek amacıyla geliştirilmiş bir **IP Engelleme Paneli**'dir. 

Reklam bütçenizi rakiplerden veya botlardan koruyarak sadece gerçek müşterilere harcanmasını sağlar.

## ✨ Temel Özellikler

* **Kullanıcı ve Tıklama Analizi:** Reklama tıklayıp siteye giren kullanıcıları panelde listeler.
* **Akıllı Bot Skoru:** Ziyaretçilerin web sitesinde geçirdiği süreye ve site içi tıklama davranışlarına göre otomatik bir "Bot Skoru" hesaplar.
* **Otomatik Maliyet Koruması (Günlük Limit):** Bir kullanıcı gün içerisinde reklamlara 3'ten fazla tıklama yaparsa (bütçe sömürüsünü engellemek için) web sitesine erişimi otomatik olarak engellenir.
* **Gelişmiş Bot Engelleme:** İnsan davranışları sergilemeyen, gerçek dışı bot kullanıcıların erişimi anında otomatik olarak kesilir.
* **Manuel Kontrol:** Sistem yöneticisi dilerse şüpheli gördüğü IP adreslerini panel üzerinden tek tıkla manuel olarak engelleyebilir.

## 💻 Kullanılan Teknolojiler
* PHP *(Backend ve IP/Oturum yönetimi)*
* MySQL *(Ziyaretçi logları ve skor kayıtları)*
* JavaScript *(Site içi süre ve tıklama dinleme işlemleri)*
* HTML/CSS *(Yönetim Paneli Arayüzü)*

## 📸 Ekran Görüntüleri
<img width="1920" height="903" alt="reklampaneli" src="https://github.com/user-attachments/assets/be4681af-a799-4ddb-b8aa-c03483cb761a" />

<img width="1920" height="885" alt="reklampaneli2" src="https://github.com/user-attachments/assets/1631365e-ecae-43e8-81d4-df02dc4f78b7" />


## 🚀 Kurulum ve Kullanım
1. Veritabanı dosyasını (`sql` dosyası) sunucunuza aktarın.
2. `config.php` dosyası içerisinden kendi veritabanı bilgilerinizi girin.
3. Panelin ayarlar kısmındaki adımları takip edin.
