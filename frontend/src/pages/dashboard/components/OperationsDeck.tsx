import { FileText, Package, Percent, Plus, Radio, Users } from 'react-feather';
import { useNavigate } from 'react-router-dom';
import { useColorScheme } from '$app/common/colors';
import { Card } from '$app/components/cards';
import { Button } from '$app/components/forms';

const routes = [
  {
    title: 'Ürün envanteri',
    description: 'Takip edilen ürünleri, birimleri ve referans maliyetleri yönetin.',
    action: 'Ürünleri aç',
    to: '/products',
    icon: Package,
    index: 'ENVANTER_01',
  },
  {
    title: 'Proforma merkezi',
    description: 'Müşteri tekliflerini hazırlayın, gözden geçirin ve PDF çıktısı alın.',
    action: 'Teklifleri aç',
    to: '/quotes',
    icon: FileText,
    index: 'TEKLİF_02',
  },
  {
    title: 'Tedarik ağı',
    description: 'Tedarikçi kayıtlarını ve satın alma akışlarını tek bağlamda izleyin.',
    action: 'Tedarikçileri aç',
    to: '/vendors',
    icon: Users,
    index: 'TEDARİK_03',
  },
];

export function OperationsDeck() {
  const navigate = useNavigate();
  const colors = useColorScheme();

  return (
    <section className="mb-8 grid grid-cols-1 gap-4 xl:grid-cols-12">
      <Card
        className="xl:col-span-5 overflow-hidden"
        style={{
          background:
            'linear-gradient(135deg, #071726 0%, #0d3150 58%, #0a638e 150%)',
          borderColor: '#174865',
          color: '#f5fbff',
        }}
        withoutBodyPadding
      >
        <div className="relative overflow-hidden px-6 py-6 sm:px-7">
          <div className="absolute -right-12 -top-16 h-48 w-48 rounded-full border border-sky-300/20" />
          <div className="absolute -right-2 -top-8 h-28 w-28 rounded-full border border-sky-300/20" />
          <div className="relative flex items-start justify-between gap-5">
            <div>
              <span className="text-[10px] font-extrabold tracking-[0.18em] text-sky-200">
                FİYAT İSTİHBARATI
              </span>
              <h3 className="mt-2 max-w-md text-2xl font-semibold tracking-tight">
                Kritik maliyet hareketlerini tek bir operasyon ekranında yönetin.
              </h3>
              <p className="mt-3 max-w-lg text-sm leading-6 text-sky-100/80">
                Ürün, tedarikçi ve proforma akışını birlikte görün. Fiyat geçmişi
                kaydı eklendikçe değişim uyarıları bu alanda önceliklendirilir.
              </p>
            </div>
            <Radio className="shrink-0 text-sky-300" size={34} />
          </div>
          <div className="relative mt-6 flex flex-wrap gap-3">
            <Button to="/products/create" className="!border-sky-300 !bg-sky-400 !text-[#071726] hover:!bg-sky-300">
              <Plus size={16} /> Ürün ekle
            </Button>
            <Button to="/quotes/create" type="secondary" className="!border-sky-100/40 !bg-transparent !text-white hover:!bg-white/10">
              <FileText size={16} /> Proforma oluştur
            </Button>
          </div>
        </div>
      </Card>

      <Card
        className="xl:col-span-3"
        style={{ borderColor: colors.$24 }}
        withoutBodyPadding
      >
        <div className="p-6">
          <div className="flex items-center justify-between">
            <span className="bisavunma-section-label">Vergi motoru</span>
            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-sky-50 text-[#0d6f9f] dark:bg-sky-400/10 dark:text-sky-300">
              <Percent size={18} />
            </span>
          </div>
          <p className="mt-5 text-3xl font-semibold tracking-tight">KDV hazır</p>
          <p className="mt-2 text-sm leading-6" style={{ color: colors.$22 }}>
            Satır tutarı, indirim, KDV ve genel toplam otomatik hesaplanır. Türkiye başlangıç ayarları KDV %20 ve %10 oranlarını içerir.
          </p>
          <button
            type="button"
            onClick={() => navigate('/settings/tax_settings')}
            className="mt-5 text-sm font-semibold text-[#0d6f9f] underline-offset-4 hover:underline dark:text-sky-300"
          >
            KDV ayarlarını aç
          </button>
        </div>
      </Card>

      <Card
        className="xl:col-span-4"
        style={{ borderColor: colors.$24 }}
        withoutBodyPadding
      >
        <div className="flex h-full flex-col p-6">
          <span className="bisavunma-section-label">Hızlı erişim</span>
          <div className="mt-3 divide-y" style={{ borderColor: colors.$21 }}>
            {routes.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  type="button"
                  key={item.to}
                  onClick={() => navigate(item.to)}
                  className="group flex w-full items-start gap-3 py-3 text-left first:pt-1"
                >
                  <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#e8f4fa] text-[#0d6f9f] transition group-hover:bg-[#0d6f9f] group-hover:text-white dark:bg-sky-400/10 dark:text-sky-300">
                    <Icon size={16} />
                  </span>
                  <span className="min-w-0">
                    <span className="block text-[10px] font-bold tracking-[0.13em] text-[#6d8898]">{item.index}</span>
                    <span className="block text-sm font-semibold">{item.title}</span>
                    <span className="block text-xs leading-5" style={{ color: colors.$22 }}>{item.description}</span>
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </Card>
    </section>
  );
}
