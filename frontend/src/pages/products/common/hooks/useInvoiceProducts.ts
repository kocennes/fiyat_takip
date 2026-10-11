/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useSetAtom, useStore } from 'jotai';
import { useNavigate } from 'react-router-dom';
import { blankLineItem } from '$app/common/constants/blank-line-item';
import { roundToPrecision } from '$app/common/helpers/invoices/round';
import {
  DocumentCurrency,
  productLineAmounts,
} from '$app/common/helpers/product-currency';
import { toast } from '$app/common/helpers/toast/toast';
import { useCurrentCompany } from '$app/common/hooks/useCurrentCompany';
import { useResolveDocumentCurrency } from '$app/common/hooks/useResolveDocumentCurrency';
import { InvoiceItemType } from '$app/common/interfaces/invoice-item';
import { Product } from '$app/common/interfaces/product';
import { useBlankInvoiceQuery } from '$app/common/queries/invoices';
import { invoiceAtom } from '$app/pages/invoices/common/atoms';

interface Params {
  onlyAddToInvoice?: boolean;
}

export const useInvoiceProducts = (params?: Params) => {
  const { onlyAddToInvoice } = params || {};

  const navigate = useNavigate();

  const store = useStore();
  const company = useCurrentCompany();
  const resolveDocumentCurrency = useResolveDocumentCurrency();

  const { data: blankInvoice } = useBlankInvoiceQuery();

  const setInvoice = useSetAtom(invoiceAtom);

  // BISAVUNMA: prices are converted from the product currency into the invoice currency
  // (common/helpers/product-currency.ts). A new invoice has no client yet, so it is in the
  // company currency.
  const toLineItems = (products: Product[], document: DocumentCurrency) => {
    let missingDocumentRate = false;

    const lineItems = products.map((product) => {
      const amounts = productLineAmounts(
        product,
        document,
        company?.settings.currency_id
      );

      missingDocumentRate ||= amounts.missingDocumentRate;

      return {
        ...blankLineItem(),
        type_id: InvoiceItemType.Product,
        product_key: product.product_key,
        quantity: company?.fill_products ? product.quantity : 1,
        product_cost: amounts.product_cost,
        ...(company?.fill_products && {
          line_total: roundToPrecision(
            amounts.cost * product.quantity,
            document.precision
          ),
          cost: amounts.cost,
          notes: product.notes,
          tax_name1: product.tax_name1,
          tax_rate1: product.tax_rate1,
          tax_name2: product.tax_name2,
          tax_rate2: product.tax_rate2,
          tax_name3: product.tax_name3,
          tax_rate3: product.tax_rate3,
          tax_id: '',
          custom_value1: product.custom_value1,
          custom_value2: product.custom_value2,
          custom_value3: product.custom_value3,
          custom_value4: product.custom_value4,
        }),
      };
    });

    if (missingDocumentRate) {
      toast.info('bis_document_exchange_rate_missing');
    }

    return lineItems;
  };

  return async (products: Product[]) => {
    if (blankInvoice) {
      if (!onlyAddToInvoice) {
        const lineItems = toLineItems(
          products,
          await resolveDocumentCurrency(blankInvoice, 'client_id')
        );

        setInvoice({ ...blankInvoice, line_items: lineItems });

        navigate('/invoices/create?action=invoice_product');
      } else {
        const lineItems = toLineItems(
          products,
          await resolveDocumentCurrency(store.get(invoiceAtom), 'client_id')
        );

        setInvoice(
          (current) =>
            current && {
              ...current,
              line_items: [...current.line_items, ...lineItems],
            }
        );
      }
    }
  };
};
