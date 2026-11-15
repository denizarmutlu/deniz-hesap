<?php
/**
 * Yardım sayfası ve sayfa içi ipuçları.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ayarlar'dan sonra gelsin diye geç öncelikle eklenir.
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'hk-panel', 'Yardım', 'Yardım', HK_CAP, 'hk-yardim', 'hk_sayfa_yardim' );
	},
	20
);

/** Alan başlığının yanına konan küçük "?" işareti: üzerine gelince açıklama, tıklayınca Yardım'daki bölüm. */
function hk_ipucu( $metin, $bolum = '' ) {
	return ' <a class="hk-ipucu" href="' . esc_url( hk_url( 'yardim' ) . ( $bolum ? '#' . $bolum : '' ) ) . '" title="' . esc_attr( $metin ) . '" aria-label="' . esc_attr( $metin ) . '">?</a>';
}

function hk_yardim_bolumleri() {
	return array(
		'musteri'  => array(
			'Yeni müşteri ve hizmet eklemek',
			'<ol>
				<li><strong>Müşteriler → Yeni müşteri</strong>: ad yeterlidir. Telefon girerseniz WhatsApp düğmesi, e-posta girerseniz e-posta düğmesi çalışır.</li>
				<li>Müşterinin sayfasında <strong>Hizmet ekle</strong>: tür (alan adı, hosting, SSL, diğer), ad, kayıt firması, bitiş tarihi.</li>
				<li><strong>Yıllık maliyet</strong> sizin firmaya ödediğiniz, <strong>müşteri fiyatı</strong> müşteriden aldığınız tutardır. İkisi de sonradan girilebilir.</li>
			</ol>
			<p><strong>Hosting eklerken:</strong> "Bağlı alan adı" seçerseniz alan adı yenilendiğinde hostingin tarihi de aynı güne taşınır; tarihi yine elle değiştirebilirsiniz. Hosting bayi paketinizden veriliyorsa yıllık maliyeti boş bırakın: maliyet paketten paylaştırılır.</p>
			<p>Kendi alan adlarınız "Kendi hesabım" işaretli hesapta durur; bu hesapta gelir beklenmez, yalnızca gider izlenir.</p>',
		),
		'yenile'   => array(
			'Alan adı yenilediğimde',
			'<p>Hizmetin yanındaki <strong>Yenile</strong> düğmesi üç işi birlikte yapar:</p>
			<ol>
				<li>Bitiş tarihini ilerletir (öneri: bir yıl sonrası; değiştirebilirsiniz).</li>
				<li>Firmaya ödediğiniz tutarı <strong>gider</strong> olarak kaydeder. Girdiğiniz tutar hizmetin yeni yıllık maliyeti olur.</li>
				<li>Müşteri için <strong>alacak</strong> kaydı açar. Önerilen tutar: önceki fiyat × (1 + zam oranı). Oranı o ekranda ya da Ayarlar\'da değiştirebilirsiniz.</li>
			</ol>
			<p>Gider ya da alacak istemiyorsanız ilgili kutunun işaretini kaldırın. Alacağı "İstenecek", "İstendi" ya da "Ödendi" olarak açabilirsiniz.</p>
			<p>Yalnızca tarihi düzeltmek istiyorsanız <strong>Düzenle</strong> ile bitiş tarihini değiştirin; kayıt açılmaz.</p>',
		),
		'tahsilat' => array(
			'Müşteriden ödeme istemek ve tahsil etmek',
			'<p>Bir alacak üç durumdan birindedir:</p>
			<ul>
				<li><strong>İstenecek</strong>: kayıt açıldı, müşteriye henüz söylenmedi.</li>
				<li><strong>İstendi</strong>: müşteriden istendi, ödeme bekleniyor.</li>
				<li><strong>Ödendi</strong>: tahsil edildi. Raporlarda gelir olarak yalnızca bunlar sayılır.</li>
			</ul>
			<p>Bekleyen alacağın yanındaki <strong>Mesaj</strong> düğmesi müşteriye gönderilecek metni hazırlar. Metni düzenleyip kopyalayabilir, WhatsApp ya da e-posta ile açabilirsiniz; gönder tuşuna siz basarsınız. Şablon Ayarlar\'dadır; banka bilginizi oraya ekleyebilirsiniz.</p>
			<p>Mesajı gönderdikten sonra <strong>Hatırlatıldı olarak işaretle</strong> derseniz hatırlatma sayısı bir artar. Ödeme gelince <strong>Ödendi</strong> düğmesi kaydı bugünün tarihiyle kapatır; tarih farklıysa Düzenle ile değiştirin.</p>',
		),
		'paket'    => array(
			'Hosting paketi ödediğimde',
			'<p>Bayi hosting paketiniz kendi hesabınızda "Hosting paketi" işaretli bir hizmettir. Ödeme yapınca bu hizmette <strong>Yenile</strong> deyin: tutar gider olarak kaydedilir, kategori kendiliğinden "Hosting paketi" olur.</p>
			<p><strong>Dağıtım:</strong> paketin yıllık gideri, aynı sağlayıcıda hostingi olan müşterilere <strong>pay sayısına</strong> göre eşit bölünür. Bir müşterinin üç hosting hesabı varsa üç pay alır. Kendi siteleriniz ve "ücretini müşteri ödüyor" işaretli hostingler pay almaz.</p>
			<p><strong>Tahmini gider:</strong> içinde bulunduğunuz yılın paket ödemesini henüz yapmadıysanız raporlar tahmin gösterir: önceki yılın paket gideri × (1 + beklenen artış). Ödemeyi kaydettiğinizde tahmin kalkar, yerine gerçek tutar geçer. Beklenen artış oranı Ayarlar\'dadır.</p>
			<p>Yıl içinde hosting fiyatı verirken Hizmetler sayfasında hostingin maliyet sütununda görünen "~" işaretli tutarı taban alın; bu, o kayda düşen pay maliyetidir.</p>
			<p>Yıl içinde pakete ek ödeme yaptıysanız Hareketler → Yeni hareket ile gider girin, kategori olarak "Hosting paketi" seçin; hesabınızda tek paket varsa kayıt ona kendiliğinden bağlanır.</p>',
		),
		'gecmis'   => array(
			'Geçmiş yılların kayıtlarını girmek',
			'<p><strong>Hareketler → Toplu giriş</strong> ekranında bir seferde 12 satır girebilirsiniz. Tutarı boş satırlar atlanır; kayıtlar "ödendi" olarak eklenir.</p>
			<p><strong>Tarih ve dönem:</strong> tarihi biliyorsanız yalnızca tarihi yazın, dönem tarihin yılı olur. Tarihi bilmiyorsanız tarihi boş bırakıp dönemi (yıl) yazın.</p>
			<p>Eski bir hosting firmasının ödemelerini dağıtıma sokmak için önce kendi hesabınıza o firmanın adıyla "Hosting paketi" işaretli bir hizmet ekleyin, sonra giderleri o hizmete bağlayın. O firmada bugün müşteri hostingi yoksa gider dağıtılmaz, sizin gideriniz olarak kalır.</p>',
		),
		'rapor'    => array(
			'Raporları okumak',
			'<ul>
				<li><strong>Tahsil edilen</strong>: "Ödendi" durumundaki gelirler. Kaydın dönem yılına yazılır.</li>
				<li><strong>Gider</strong>: firmalara ödedikleriniz; kendi alan adlarınız ve hosting paketi dahildir.</li>
				<li><strong>Net kâr</strong>: tahsil edilen − gider. <strong>Marj</strong>: net kârın tahsil edilene oranı.</li>
				<li><strong>Tahmini gider</strong>: henüz ödenmemiş hosting paketi için tahmin. Net kâra dahil değildir; "tahmin dahil net" sütununda ayrıca gösterilir.</li>
				<li><strong>Hosting payı</strong>: paket giderinin o müşteriye düşen kısmı. Sizin satırınızda eksi görünür, çünkü ödediğiniz tutar müşterilere aktarılmıştır; toplam kâr değişmez.</li>
				<li><strong>Bekleyen alacak</strong>: istenmiş ya da istenecek, henüz ödenmemiş tutarlar. Gelire dahil değildir.</li>
			</ul>
			<p><strong>Birimler:</strong> "TL" tutarları olduğu gibi gösterir. "Dolar karşılığı" her yılın tutarını o yılın ortalama kuruyla çevirir. "Bugünkü fiyatlarla TL" geçmiş yılların tutarlarını aradaki yılların TÜFE oranlarıyla bugüne taşır; yıllar arasında gerçek karşılaştırma için bunu kullanın. Kur ve TÜFE değerleri Ayarlar\'dadır; içinde bulunulan yılın değerleri geçicidir.</p>
			<p>Pay sayıları bugünkü hosting kayıtlarından alınır. Geçmiş yıllarda müşteri sayınız farklıysa o yılların müşteri bazındaki hosting payı yaklaşıktır.</p>',
		),
		'kontrol'  => array(
			'Kontrol sayfası',
			'<p>Tarihi, tutarı ya da durumu belirsiz kayıtlar burada toplanır. Kayıt silinmez, yalnızca işaretlidir.</p>
			<ul>
				<li><strong>Onayla</strong>: kayıt doğru; işareti kaldırır, başka bir şeyi değiştirmez.</li>
				<li><strong>+1 yıl</strong> (hizmetlerde): tarihi bugünü geçene kadar birer yıl ilerletir ve işareti kaldırır. Gider ya da alacak kaydı açmaz; yenilenmiş ama tarihi eski kalmış alan adları içindir.</li>
				<li><strong>Düzenle</strong>: kaydı açar; düzelttikten sonra "Kontrol edilecek" kutusunun işaretini kaldırıp kaydedin.</li>
			</ul>
			<p>Tarihi geçmişte kalmış ve kontrol bekleyen hizmetler "Yaklaşan yenilemeler" listesinde görünmez; gerçek bir gecikme olmayabilir.</p>',
		),
		'eposta'   => array(
			'Hatırlatma e-postaları',
			'<p>Eklenti her sabah 08:00 civarında kontrol eder ve şu durumlarda e-posta gönderir:</p>
			<ul>
				<li>Bir hizmetin bitişine Ayarlar\'da yazan gün sayılarından biri kaldıysa (varsayılan: 30, 7 ve 1 gün).</li>
				<li>Haftalık özet açıksa her pazartesi: yaklaşan yenilemeler ve bekleyen alacaklar.</li>
			</ul>
			<p><strong>E-posta gelmiyorsa:</strong> Ayarlar → Araçlar bölümünde "Özet e-postasını şimdi gönder" ile deneyin. Hata verirse sitenin e-posta (SMTP) ayarına bakın. Deneme gelip günlük e-posta gelmiyorsa aynı bölümde "sıradaki çalışma" zamanına bakın; WordPress zamanlanmış görevleri siteye ziyaret geldikçe çalışır, gerekirse oradaki cron komutunu sunucu panelinize ekleyin.</p>',
		),
		'yedek'    => array(
			'Yedek ve güvenlik',
			'<ul>
				<li>Kayıtlar sitenin veritabanında üç tabloda durur: müşteriler, hizmetler, hareketler. Eklenti güncellendiğinde, devre dışı bırakıldığında ya da silindiğinde bu tablolar silinmez.</li>
				<li>Sitenizin veritabanı yedeği bu tabloları da kapsar. Ayrıca Hareketler → CSV indir ile kayıtları dosya olarak alabilirsiniz.</li>
				<li>Eklentiyi yalnızca yönetici hesapları görür. O hesaba giren herkes tüm kayıtları görebilir; güçlü parola ve iki adımlı giriş kullanın.</li>
				<li>Kurulu sürüm ve yapılan değişiklikler Ayarlar sayfasının sonundadır.</li>
			</ul>',
		),
		'sozluk'   => array(
			'Sözlük',
			'<dl class="hk-sozluk">
				<dt>Dönem</dt><dd>Kaydın hangi yılın hesabına yazıldığı. Raporlar döneme göre toplar.</dd>
				<dt>Hizmet</dt><dd>Takip edilen alan adı, hosting, SSL ya da başka bir yıllık kalem.</dd>
				<dt>Hareket</dt><dd>Bir gelir (müşteriden alınan ya da alınacak) ya da gider (firmaya ödenen) kaydı.</dd>
				<dt>Kategori</dt><dd>Hareketin türü: yenileme, yeni web sitesi, site yenileme, hosting paketi, diğer.</dd>
				<dt>Paket</dt><dd>Gideri müşterilere dağıtılan toplu hosting hizmeti (ör. bayi paketi).</dd>
				<dt>Pay</dt><dd>Bir hosting kaydının paketten aldığı hisse sayısı; genellikle hosting hesabı sayısı.</dd>
				<dt>Tahmini gider</dt><dd>Bu yıl henüz ödenmemiş paket için hesaplanan beklenen tutar.</dd>
				<dt>Kontrol</dt><dd>Doğruluğundan emin olunmayan kayıtlara konan işaret.</dd>
			</dl>',
		),
	);
}

function hk_sayfa_yardim() {
	$bolumler = hk_yardim_bolumleri();
	hk_baslik( 'Yardım' );
	echo '<div class="hk-yardim"><p>Sık yapılan işler için kısa anlatımlar. Alan başlıklarının yanındaki <span class="hk-ipucu">?</span> işaretleri de buradaki ilgili bölüme götürür.</p>';
	echo '<ol class="hk-y-icindekiler">';
	foreach ( $bolumler as $id => $b ) {
		printf( '<li><a href="#%s">%s</a></li>', esc_attr( $id ), esc_html( $b[0] ) );
	}
	echo '</ol>';
	foreach ( $bolumler as $id => $b ) {
		printf( '<section id="%s"><h2>%s</h2>%s<p class="hk-y-yukari"><a href="#wpbody">↑ başa dön</a></p></section>', esc_attr( $id ), esc_html( $b[0] ), wp_kses_post( $b[1] ) );
	}
	echo '<p class="hk-alt">' . esc_html( HK_AD ) . ' sürüm ' . esc_html( HK_VER ) . '</p></div></div>';
}
