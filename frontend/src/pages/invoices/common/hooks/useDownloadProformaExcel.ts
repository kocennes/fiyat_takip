import { endpoint } from '$app/common/helpers';
import { request } from '$app/common/helpers/request';
import { toast } from '$app/common/helpers/toast/toast';
import { Invoice } from '$app/common/interfaces/invoice';

export function useDownloadProformaExcel() {
  return (invoice: Invoice) => {
    toast.processing();

    request(
      'GET',
      endpoint('/api/v1/invoices/:id/proforma.xlsx', { id: invoice.id }),
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

        link.download = match?.[1] || `Proforma-${invoice.number}.xlsx`;
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
