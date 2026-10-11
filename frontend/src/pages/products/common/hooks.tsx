/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { EntityState } from '$app/common/enums/entity-state';
import { date, getEntityState } from '$app/common/helpers';
import { route } from '$app/common/helpers/route';
import { toast } from '$app/common/helpers/toast/toast';
import { useFormatMoney } from '$app/common/hooks/money/useFormatMoney';
import { useCurrentCompanyDateFormats } from '$app/common/hooks/useCurrentCompanyDateFormats';
import { Product } from '$app/common/interfaces/product';
import { ValidationBag } from '$app/common/interfaces/validation-bag';
import { DropdownElement } from '$app/components/dropdown/DropdownElement';
import { EntityStatus } from '$app/components/EntityStatus';
import { Icon } from '$app/components/icons/Icon';
import { DataTableColumnsExtended } from '$app/pages/invoices/common/hooks/useInvoiceColumns';
import { Dispatch, SetStateAction } from 'react';
import { useTranslation } from 'react-i18next';
import {
  MdArchive,
  MdControlPointDuplicate,
  MdDelete,
  MdEdit,
  MdRestore,
} from 'react-icons/md';
import { useNavigate } from 'react-router-dom';
import { productAtom } from './atoms';
import { bulk } from '$app/common/queries/products';
import { Divider } from '$app/components/cards/Divider';
import { Tooltip } from '$app/components/Tooltip';
import { useEntityCustomFields } from '$app/common/hooks/useEntityCustomFields';
import { useSetAtom } from 'jotai';
import { useReactSettings } from '$app/common/hooks/useReactSettings';
import { useEntityPageIdentifier } from '$app/common/hooks/useEntityPageIdentifier';
import { BiPlusCircle } from 'react-icons/bi';
import { useInvoiceProducts } from './hooks/useInvoiceProducts';
import { usePurchaseOrderProducts } from './hooks/usePurchaseOrderProducts';
import { $refetch } from '$app/common/hooks/useRefetch';
import { useHasPermission } from '$app/common/hooks/permissions/useHasPermission';
import { useDisableNavigation } from '$app/common/hooks/useDisableNavigation';
import { DynamicLink } from '$app/components/DynamicLink';
import { useFormatCustomFieldValue } from '$app/common/hooks/useFormatCustomFieldValue';
import {
  extractTextFromHTML,
  sanitizeHTML,
} from '$app/common/helpers/html-string';
import { useFormatNumber } from '$app/common/hooks/useFormatNumber';
import classNames from 'classnames';
import { normalizeColumnName } from '$app/common/helpers/data-table';
import { TagPills } from '$app/components/tags/TagPills';
import { useCurrentCompany } from '$app/common/hooks/useCurrentCompany';
import { useResolveCurrency } from '$app/common/hooks/useResolveCurrency';
import { useFormatExchangeRate } from '$app/common/hooks/useFormatExchangeRate';
import {
  hasProductExchangeRate,
  isCompanyCurrency,
  productExchangeRate,
  productProfit,
} from '$app/common/helpers/product-currency';

export const defaultColumns: string[] = [
  'product_key',
  'description',
  'buy_price',
  'price',
  'currency',
  'exchange_rate',
  'bis_purchase_price_try',
  'bis_sale_price_try',
  'bis_profit_try',
  'bis_profit_percent',
  'quantity',
];

// BISAVUNMA "Fiyat envanteri": TL columns computed from cost/price × exchange_rate
// (common/helpers/product-currency.ts); they are not stored, so the API ignores them as sort keys.
type ProductTableRow = Product & {
  bis_purchase_price_try?: number;
  bis_sale_price_try?: number;
  bis_profit_try?: number;
  bis_profit_percent?: number;
};

/** Percentage in Turkish notation: %25,50 / -%4,00. */
export function useFormatPercent() {
  const formatNumber = useFormatNumber();

  return (value: number) =>
    `${value < 0 ? '-' : ''}%${formatNumber(Math.abs(value))}`;
}

export function useAllProductColumns() {
  const [firstCustom, secondCustom, thirdCustom, fourthCustom] =
    useEntityCustomFields({
      entity: 'product',
    });

  const productColumns = [
    'product_key',
    'description',
    'buy_price',
    'price',
    'currency',
    'exchange_rate',
    'bis_purchase_price_try',
    'bis_sale_price_try',
    'bis_profit_try',
    'bis_profit_percent',
    'quantity',
    'archived_at',
    // 'assigned_to', @Todo: Relationship not included.
    'created_at',
    // 'created_by', @Todo: Relationship not included.
    firstCustom,
    secondCustom,
    thirdCustom,
    fourthCustom,
    'documents',
    'entity_state',
    'is_deleted',
    'notification_threshold',
    'stock_quantity',
    'tax_name1',
    'tax_name2',
    'tax_name3',
    'tax_rate1',
    'tax_rate2',
    'tax_rate3',
    'updated_at',
    'tags',
  ] as const;

  return productColumns.map((column) => normalizeColumnName(column));
}

export function useProductColumns() {
  const { t } = useTranslation();

  const productColumns = useAllProductColumns();
  type ProductColumns = (typeof productColumns)[number];

  const { dateFormat } = useCurrentCompanyDateFormats();

  const company = useCurrentCompany();
  const formatMoney = useFormatMoney();
  const formatNumber = useFormatNumber();
  const formatPercent = useFormatPercent();
  const reactSettings = useReactSettings();
  const resolveCurrency = useResolveCurrency();
  const disableNavigation = useDisableNavigation();
  const formatExchangeRate = useFormatExchangeRate();
  const formatCustomFieldValue = useFormatCustomFieldValue();

  const [firstCustom, secondCustom, thirdCustom, fourthCustom] =
    useEntityCustomFields({
      entity: 'product',
    });

  const companyCurrencyId = company?.settings.currency_id;

  // Alış / satış fiyatı are shown in the product currency.
  const formatProductMoney = (value: string | number, product: Product) =>
    formatMoney(
      value,
      product.company?.settings.country_id,
      product.currency_id || product.company?.settings.currency_id
    );

  // TL amounts; a foreign-currency product without a rate has none.
  const formatTryAmount = (
    product: Product,
    amount: 'cost' | 'price' | 'profit'
  ) => {
    if (!hasProductExchangeRate(product, companyCurrencyId)) {
      return '—';
    }

    const value = productProfit(product, companyCurrencyId)[amount];

    return (
      <span className={classNames({ 'text-red-600': value < 0 })}>
        {formatMoney(value, company?.settings.country_id, companyCurrencyId)}
      </span>
    );
  };

  const columns: DataTableColumnsExtended<ProductTableRow, ProductColumns> = [
    {
      column: 'product_key',
      id: 'product_key',
      label: t('product'),
      format: (value, product) => (
        <span className="inline-flex items-center space-x-4">
          <EntityStatus entity={product} />

          <DynamicLink
            to={route('/products/:id/edit', { id: product.id })}
            renderSpan={disableNavigation('product', product)}
          >
            {value}
          </DynamicLink>
        </span>
      ),
    },
    {
      column: 'description',
      id: 'notes',
      label: t('notes'),
      format: (value) => (
        <Tooltip
          width="auto"
          tooltipElement={
            <div className="w-full max-h-48 overflow-auto whitespace-normal break-all">
              <article
                className={classNames('prose prose-sm', {
                  'prose-invert': !reactSettings?.dark_mode,
                })}
                dangerouslySetInnerHTML={{
                  __html: sanitizeHTML(value as string),
                }}
              />
            </div>
          }
        >
          <span>
            {extractTextFromHTML(sanitizeHTML(value as string)).slice(0, 50)}
          </span>
        </Tooltip>
      ),
    },
    {
      column: 'buy_price',
      id: 'cost',
      label: t('buy_price'),
      format: (value, product) => formatProductMoney(value, product),
    },
    {
      column: 'price',
      id: 'price',
      label: t('bis_sale_price'),
      format: (value, product) => formatProductMoney(value, product),
    },
    {
      column: 'currency',
      id: 'currency_id',
      label: t('currency'),
      format: (value, product) => {
        const currency = resolveCurrency(
          product.currency_id || companyCurrencyId || ''
        );

        return currency?.code ?? '';
      },
    },
    {
      column: 'exchange_rate',
      id: 'exchange_rate',
      label: t('exchange_rate'),
      format: (value, product) =>
        hasProductExchangeRate(product, companyCurrencyId)
          ? formatExchangeRate(
              productExchangeRate(product, companyCurrencyId),
              4
            )
          : '—',
    },
    {
      column: 'bis_purchase_price_try',
      id: 'bis_purchase_price_try',
      label: t('bis_purchase_price_try'),
      format: (value, product) => formatTryAmount(product, 'cost'),
    },
    {
      column: 'bis_sale_price_try',
      id: 'bis_sale_price_try',
      label: t('bis_sale_price_try'),
      format: (value, product) => formatTryAmount(product, 'price'),
    },
    {
      column: 'bis_profit_try',
      id: 'bis_profit_try',
      label: t('bis_profit_try'),
      format: (value, product) => formatTryAmount(product, 'profit'),
    },
    {
      column: 'bis_profit_percent',
      id: 'bis_profit_percent',
      label: t('bis_profit_percent'),
      format: (value, product) => {
        if (!hasProductExchangeRate(product, companyCurrencyId)) {
          return '—';
        }

        const { profitPercent } = productProfit(product, companyCurrencyId);

        return (
          <span className={classNames({ 'text-red-600': profitPercent < 0 })}>
            {formatPercent(profitPercent)}
          </span>
        );
      },
    },
    {
      column: 'quantity',
      id: 'quantity',
      label: t('default_quantity'),
      format: (value) => formatNumber(value),
    },
    {
      column: 'archived_at',
      id: 'archived_at',
      label: t('archived_at'),
      format: (value) => date(value, dateFormat),
    },
    {
      column: 'created_at',
      id: 'created_at',
      label: t('created_at'),
      format: (value) => date(value, dateFormat),
    },
    {
      column: firstCustom,
      id: 'custom_value1',
      label: firstCustom,
      format: (value) => formatCustomFieldValue('product1', value?.toString()),
    },
    {
      column: secondCustom,
      id: 'custom_value2',
      label: secondCustom,
      format: (value) => formatCustomFieldValue('product2', value?.toString()),
    },
    {
      column: thirdCustom,
      id: 'custom_value3',
      label: thirdCustom,
      format: (value) => formatCustomFieldValue('product3', value?.toString()),
    },
    {
      column: fourthCustom,
      id: 'custom_value4',
      label: fourthCustom,
      format: (value) => formatCustomFieldValue('product4', value?.toString()),
    },
    {
      column: 'documents',
      id: 'documents',
      label: t('documents'),
      format: (value, product) => product.documents.length,
    },
    {
      column: 'entity_state',
      id: 'id',
      label: t('entity_state'),
      format: (value, product) => <EntityStatus entity={product} />,
    },
    {
      column: 'is_deleted',
      id: 'is_deleted',
      label: t('is_deleted'),
      format: (value, product) => (product.is_deleted ? t('yes') : t('no')),
    },
    {
      column: 'notification_threshold',
      id: 'stock_notification_threshold',
      label: t('notification_threshold'),
    },
    {
      column: 'stock_quantity',
      id: 'in_stock_quantity',
      label: t('stock_quantity'),
    },
    {
      column: 'tax_name1',
      id: 'tax_name1',
      label: t('tax_name1'),
    },
    {
      column: 'tax_name2',
      id: 'tax_name2',
      label: t('tax_name2'),
    },
    {
      column: 'tax_name3',
      id: 'tax_name3',
      label: t('tax_name3'),
    },
    {
      column: 'tax_rate1',
      id: 'tax_rate1',
      label: t('tax_rate1'),
      format: (value) => formatNumber(value),
    },
    {
      column: 'tax_rate2',
      id: 'tax_rate2',
      label: t('tax_rate2'),
      format: (value) => formatNumber(value),
    },
    {
      column: 'tax_rate3',
      id: 'tax_rate3',
      label: t('tax_rate3'),
      format: (value) => formatNumber(value),
    },
    {
      column: 'updated_at',
      id: 'updated_at',
      label: t('updated_at'),
      format: (value) => date(value, dateFormat),
    },
    {
      column: 'tags',
      id: 'product_tag_ids',
      label: t('tags'),
      format: (value, product) => <TagPills tags={product.tags} />,
    },
  ];

  const list: string[] =
    reactSettings?.react_table_columns?.product || defaultColumns;

  return columns
    .filter((column) => list.includes(normalizeColumnName(column.column)))
    .sort(
      (a, b) =>
        list.indexOf(normalizeColumnName(a.column)) -
        list.indexOf(normalizeColumnName(b.column))
    );
}

interface ActionsParams {
  showEditAction?: boolean;
}

export function useActions(params?: ActionsParams) {
  const [t] = useTranslation();

  const { showEditAction } = params ?? {};

  const navigate = useNavigate();

  const hasPermission = useHasPermission();

  const setProduct = useSetAtom(productAtom);

  const invoiceProducts = useInvoiceProducts();

  const purchaseOrderProducts = usePurchaseOrderProducts();

  const { isEditPage } = useEntityPageIdentifier({
    entity: 'product',
    editPageTabs: ['documents', 'product_fields'],
  });

  const cloneToProduct = (product: Product) => {
    setProduct({ ...product, id: '', documents: [] });

    navigate('/products/create?action=clone');
  };

  const handleResourcefulAction = (
    action: 'archive' | 'restore' | 'delete',
    id: string
  ) => {
    toast.processing();

    bulk([id], action).then(() => {
      toast.success(`${action}d_product`);

      $refetch(['products']);
    });
  };

  const actions = [
    (product: Product) =>
      showEditAction && (
        <DropdownElement
          onClick={() =>
            navigate(route('/products/:id/edit', { id: product.id }))
          }
          icon={<Icon element={MdEdit} />}
        >
          {t('edit')}
        </DropdownElement>
      ),
    () => showEditAction && <Divider withoutPadding />,
    (product: Product) =>
      !product.is_deleted &&
      hasPermission('create_invoice') && (
        <DropdownElement
          onClick={() => invoiceProducts([product])}
          icon={<Icon element={BiPlusCircle} />}
        >
          {t('new_invoice')}
        </DropdownElement>
      ),
    (product: Product) =>
      !product.is_deleted &&
      hasPermission('create_purchase_order') && (
        <DropdownElement
          onClick={() => purchaseOrderProducts([product])}
          icon={<Icon element={BiPlusCircle} />}
        >
          {t('new_purchase_order')}
        </DropdownElement>
      ),
    (product: Product) =>
      !product.is_deleted &&
      hasPermission('create_product') && (
        <DropdownElement
          onClick={() => cloneToProduct(product)}
          icon={<Icon element={MdControlPointDuplicate} />}
        >
          {t('clone')}
        </DropdownElement>
      ),
    () => isEditPage && <Divider withoutPadding />,
    (product: Product) =>
      getEntityState(product) === EntityState.Active &&
      isEditPage && (
        <DropdownElement
          onClick={() => handleResourcefulAction('archive', product.id)}
          icon={<Icon element={MdArchive} />}
        >
          {t('archive')}
        </DropdownElement>
      ),
    (product: Product) =>
      (getEntityState(product) === EntityState.Archived ||
        getEntityState(product) === EntityState.Deleted) &&
      isEditPage && (
        <DropdownElement
          onClick={() => handleResourcefulAction('restore', product.id)}
          icon={<Icon element={MdRestore} />}
        >
          {t('restore')}
        </DropdownElement>
      ),
    (product: Product) =>
      (getEntityState(product) === EntityState.Active ||
        getEntityState(product) === EntityState.Archived) &&
      isEditPage && (
        <DropdownElement
          onClick={() => handleResourcefulAction('delete', product.id)}
          icon={<Icon element={MdDelete} />}
        >
          {t('delete')}
        </DropdownElement>
      ),
  ];

  return actions;
}

interface Params {
  setErrors: Dispatch<SetStateAction<ValidationBag | undefined>>;
  setProduct: Dispatch<SetStateAction<Product | undefined>>;
}

export function useHandleChange(params: Params) {
  const { setErrors, setProduct } = params;

  return (property: keyof Product, value: Product[keyof Product]) => {
    setErrors(undefined);

    setProduct((product) => product && { ...product, [property]: value });
  };
}

/** BISAVUNMA: currency defaults of a new product ('' = company currency, rate 1). */
export function useProductCurrencyDefaults() {
  const company = useCurrentCompany();

  return (product: Product): Product => ({
    ...product,
    currency_id: product.currency_id || '',
    exchange_rate: isCompanyCurrency(
      product.currency_id,
      company?.settings.currency_id
    )
      ? 1
      : Number(product.exchange_rate) || 0,
  });
}

/** BISAVUNMA: a product in a foreign currency needs a manual exchange rate > 0. */
export function useValidateProductCurrency() {
  const [t] = useTranslation();

  const company = useCurrentCompany();

  return (product: Product | undefined): ValidationBag | undefined => {
    if (
      !product ||
      hasProductExchangeRate(product, company?.settings.currency_id)
    ) {
      return undefined;
    }

    const message = t('bis_exchange_rate_required');

    return { message, errors: { exchange_rate: [message] } };
  };
}
