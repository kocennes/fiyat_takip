import { ArrowUpRight, FileText, Package, Plus, Radio, TrendingDown, TrendingUp, Users } from 'react-feather';
import { BrandLockup } from '$app/components/brand/BrandLockup';

const priceRows = [
  { product: 'RF güç modülü', supplier: 'Anka Elektronik', previous: '₺18.450', current: '₺17.980', trend: 'down', change: '%2,5 düşüş' },
  { product: 'Termal optik montaj', supplier: 'Delta Savunma', previous: '₺42.600', current: '₺44.280', trend: 'up', change: '%3,9 artış' },
  { product: 'EMI korumalı konnektör', supplier: 'Mikro Kablo', previous: '₺1.240', current: '₺1.240', trend: 'flat', change: 'Değişim yok' },
];

export function Preview() {
  return (
    <div className="min-h-screen bg-[#edf3f7] text-[#071726]">
      <header className="border-b border-white/10 bg-[#071726]">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
          <BrandLockup />
          <span className="rounded-full border border-sky-300/25 bg-sky-400/10 px-3 py-1 text-[10px] font-bold tracking-[0.16em] text-sky-200">
            YEREL ÖNİZLEME
          </span>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-6 py-8">
        <section className="overflow-hidden rounded-2xl bg-[linear-gradient(135deg,#071726_0%,#0d3150_58%,#0a638e_150%)] px-7 py-8 text-white shadow-xl shadow-slate-900/10">
          <div className="flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
            <div>
              <p className="text-[11px] font-extrabold tracking-[0.2em] text-sky-200">OPERASYON MERKEZİ</p>
              <h1 className="mt-3 text-3xl font-semibold tracking-tight">Fiyat, tedarik ve proforma tek ekranda.</h1>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-sky-100/80">
                Tedarik maliyetlerini izleyin, değişimleri değerlendirin ve KDV hesaplı proforma tekliflerinizi hazırlayın.
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <button className="inline-flex items-center gap-2 rounded-lg bg-sky-400 px-4 py-2.5 text-sm font-semibold text-[#071726]"><Plus size={16} /> Ürün ekle</button>
              <button className="inline-flex items-center gap-2 rounded-lg border border-white/25 px-4 py-2.5 text-sm font-semibold"><FileText size={16} /> Proforma oluştur</button>
            </div>
          </div>
        </section>

        <section className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {[
            ['Takip edilen ürün', '128', Package, 'Son 30 günde 14 güncelleme'],
            ['Aktif tedarikçi', '24', Users, '3 tedarikçi teklif bekliyor'],
            ['Fiyat düşüşü', '8', TrendingDown, 'Tasarruf fırsatı'],
            ['Açık proforma', '12', FileText, 'KDV otomatik hesaplanır'],
          ].map(([label, value, Icon, detail]) => {
            const MetricIcon = Icon as typeof Package;
            return (
              <article key={label as string} className="rounded-xl border border-[#c8d8e3] bg-white p-5 shadow-sm">
                <div className="flex items-center justify-between"><span className="text-xs font-bold uppercase tracking-[0.13em] text-[#54758b]">{label as string}</span><MetricIcon size={18} className="text-[#2aaee5]" /></div>
                <p className="mt-5 text-3xl font-semibold tracking-tight">{value as string}</p>
                <p className="mt-2 text-xs text-[#54758b]">{detail as string}</p>
              </article>
            );
          })}
        </section>

        <section className="mt-6 grid gap-6 xl:grid-cols-3">
          <article className="overflow-hidden rounded-xl border border-[#c8d8e3] bg-white shadow-sm xl:col-span-2">
            <div className="flex items-center justify-between border-b border-[#dce7ee] px-6 py-5">
              <div><p className="text-[10px] font-bold tracking-[0.16em] text-[#2aaee5]">FİYAT İSTİHBARATI</p><h2 className="mt-1 font-semibold">Son maliyet hareketleri</h2></div>
              <button className="inline-flex items-center gap-1 text-sm font-semibold text-[#0d6f9f]">Tümünü gör <ArrowUpRight size={15} /></button>
            </div>
            <div className="divide-y divide-[#e4edf2]">
              {priceRows.map((row) => (
                <div key={row.product} className="grid grid-cols-[1.6fr_1fr_auto] items-center gap-3 px-6 py-4">
                  <div><p className="text-sm font-semibold">{row.product}</p><p className="mt-1 text-xs text-[#54758b]">{row.supplier}</p></div>
                  <p className="text-sm text-[#54758b]"><span className="line-through">{row.previous}</span> <strong className="ml-2 text-[#071726] no-underline">{row.current}</strong></p>
                  <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${row.trend === 'down' ? 'bg-emerald-50 text-emerald-700' : row.trend === 'up' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'}`}>
                    {row.trend === 'down' ? <TrendingDown size={13} /> : row.trend === 'up' ? <TrendingUp size={13} /> : <Radio size={13} />}{row.change}
                  </span>
                </div>
              ))}
            </div>
          </article>

          <article className="rounded-xl border border-[#c8d8e3] bg-white p-6 shadow-sm">
            <p className="text-[10px] font-bold tracking-[0.16em] text-[#2aaee5]">PROFORMA KONTROLÜ</p>
            <h2 className="mt-1 font-semibold">KDV hesaplaması hazır</h2>
            <div className="mt-6 space-y-3 rounded-lg bg-[#f4f8fb] p-4 text-sm">
              <div className="flex justify-between text-[#54758b]"><span>Ara toplam</span><strong className="font-medium text-[#071726]">₺25.000,00</strong></div>
              <div className="flex justify-between text-[#54758b]"><span>KDV %20</span><strong className="font-medium text-[#071726]">₺5.000,00</strong></div>
              <div className="flex justify-between border-t border-[#c8d8e3] pt-3 text-base font-semibold"><span>Genel toplam</span><span>₺30.000,00</span></div>
            </div>
            <p className="mt-5 text-xs leading-5 text-[#54758b]">Ürün satırları, indirimler ve KDV proforma PDF’sine otomatik aktarılacak.</p>
          </article>
        </section>
      </main>
    </div>
  );
}
