import { atom, useSetAtom } from 'jotai';
import { endpoint } from '$app/common/helpers';
import { request } from '$app/common/helpers/request';
import { toast } from '$app/common/helpers/toast/toast';
import { Invoice } from '$app/common/interfaces/invoice';
import { Quote } from '$app/common/interfaces/quote';

export type ProformaExcelResource = 'invoice' | 'quote';

export interface ProformaExcelRequest {
  resource: ProformaExcelResource;
  entity: Invoice | Quote;
}

/** The document whose Excel download options are open (ProformaExcelModal). */
export const proformaExcelAtom = atom<ProformaExcelRequest | null>(null);

export interface ProformaExcelOptions {
  /** Add the internal profit table below the printed proforma. */
  profit: boolean;
  /** Currency code of the profit table (TRY, USD or the document currency). */
  currency?: string;
  /** 1 unit of the document currency in TL. */
  documentRate?: number;
  /** 1 USD in TL. */
  usdRate?: number;
}

/** Opens the Excel download options (profit table, its currency and exchange rates). */
export function useOpenProformaExcel() {
  const setRequest = useSetAtom(proformaExcelAtom);

  return (resource: ProformaExcelResource, entity: Invoice | Quote) =>
    setRequest({ resource, entity });
}

/** Downloads the proforma Excel of an invoice or quote with the chosen options. */
export function useDownloadProformaExcel() {
  return (
    resource: ProformaExcelResource,
    entity: Invoice | Quote,
    options: ProformaExcelOptions
  ) => {
    toast.processing();

    const query = new URLSearchParams({ profit: options.profit ? '1' : '0' });

    if (options.profit && options.currency) {
      query.set('currency', options.currency);
    }

    if (options.profit && options.documentRate && options.documentRate > 0) {
      query.set('document_rate', String(options.documentRate));
    }

    if (options.profit && options.usdRate && options.usdRate > 0) {
      query.set('usd_rate', String(options.usdRate));
    }

    return request(
      'GET',
      `${endpoint(`/api/v1/${resource}s/:id/proforma.xlsx`, {
        id: entity.id,
      })}?${query.toString()}`,
      {},
      { responseType: 'arraybuffer' }
    )
      .then((response) => {
        const blob = new Blob([response.data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = URL.createObjectURL(blob);
        const contentDisposition = response.headers['content-disposition'] || '';
        const match = /filename="?([^";]+)"?/i.exec(contentDisposition);
        const link = document.createElement('a');

        link.download = match?.[1] || `Proforma-${entity.number}.xlsx`;
        link.href = url;
        link.target = '_blank';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.setTimeout(() => URL.revokeObjectURL(url), 0);
      })
      .finally(() => toast.dismiss());
  };
}
