/**
 * BISAVUNMA Fiyat Takip: product currency and manual exchange rate (pure helpers).
 *
 * A product keeps `cost` (alış fiyatı) and `price` (satış fiyatı) in its own `currency_id`
 * ('' / null = company currency, TRY) plus a manually typed `exchange_rate`:
 * 1 unit of the product currency = `exchange_rate` units of the company currency (1 for TRY).
 *
 * Documents (quote/proforma, invoice, recurring invoice, credit, purchase order) keep the
 * upstream meaning of their `exchange_rate`: units of the DOCUMENT currency per 1 unit of the
 * company currency (Client::setExchangeRate() = 1 / convert(1, client -> company currency);
 * reports convert back with `amount / exchange_rate`). A product amount reaches a line as:
 *   product currency = document currency -> value (copied as is)
 *   document in the company currency     -> value × product.exchange_rate
 *   any other document currency          -> value × product.exchange_rate × document.exchange_rate
 */

import { roundToPrecision } from './invoices/round';

type CurrencyId = string | number | null | undefined;
type Amount = string | number | null | undefined;

export interface ProductCurrencyFields {
  cost?: Amount;
  price?: Amount;
  currency_id?: CurrencyId;
  exchange_rate?: Amount;
}

export interface DocumentCurrency {
  /** Document currency: the client's (vendor's for purchase orders); '' = company currency. */
  currencyId: CurrencyId;
  /** The document's `exchange_rate` (document-currency units per 1 company-currency unit). */
  exchangeRate: Amount;
  /** Decimals for converted amounts; converted amounts are not rounded when omitted. */
  precision?: number;
}

export interface ProductProfit {
  /** Alış (TL) = cost × exchange_rate */
  cost: number;
  /** Satış (TL) = price × exchange_rate */
  price: number;
  /** Kâr (TL) = satış TL − alış TL */
  profit: number;
  /** Kâr % = kâr TL / satış TL × 100 (0 when satış TL is 0) */
  profitPercent: number;
}

export interface ProductLineAmounts {
  /** Line unit price (`cost` of the line item). */
  cost: number;
  /** Line unit purchase price (`product_cost`: internal, never printed). */
  product_cost: number;
  /** The conversion needed the document rate, but the document has no usable rate (≤ 0 or 1). */
  missingDocumentRate: boolean;
}

const toNumber = (value: Amount) => {
  const number = Number(value);

  return Number.isFinite(number) ? number : 0;
};

/** True when the currency is empty (= company currency) or is the company currency. */
export function isCompanyCurrency(
  currencyId: CurrencyId,
  companyCurrencyId: CurrencyId
) {
  return !currencyId || String(currencyId) === String(companyCurrencyId);
}

/** True when the product is in the company currency or has a manual rate > 0. */
export function hasProductExchangeRate(
  product: ProductCurrencyFields,
  companyCurrencyId: CurrencyId
) {
  return (
    isCompanyCurrency(product.currency_id, companyCurrencyId) ||
    toNumber(product.exchange_rate) > 0
  );
}

/** Company-currency units per 1 product-currency unit (1 for TRY; a missing rate counts as 1, the column default). */
export function productExchangeRate(
  product: ProductCurrencyFields,
  companyCurrencyId: CurrencyId
) {
  if (isCompanyCurrency(product.currency_id, companyCurrencyId)) {
    return 1;
  }

  const rate = toNumber(product.exchange_rate);

  return rate > 0 ? rate : 1;
}

/** Purchase price, sale price and profit of a product in the company currency (TL). */
export function productProfit(
  product: ProductCurrencyFields,
  companyCurrencyId: CurrencyId
): ProductProfit {
  const rate = productExchangeRate(product, companyCurrencyId);

  const cost = toNumber(product.cost) * rate;
  const price = toNumber(product.price) * rate;
  const profit = price - cost;

  return {
    cost,
    price,
    profit,
    profitPercent: price ? (profit / price) * 100 : 0,
  };
}

function conversionPath(
  product: ProductCurrencyFields,
  document: DocumentCurrency,
  companyCurrencyId: CurrencyId
) {
  const productCurrencyId = product.currency_id || companyCurrencyId;
  const documentCurrencyId = document.currencyId || companyCurrencyId;

  if (String(productCurrencyId) === String(documentCurrencyId)) {
    return 'same';
  }

  return isCompanyCurrency(documentCurrencyId, companyCurrencyId)
    ? 'to_company'
    : 'via_company';
}

/** Converts an amount in the product currency into the document currency (rules above). */
export function convertProductAmount(
  value: Amount,
  product: ProductCurrencyFields,
  document: DocumentCurrency,
  companyCurrencyId: CurrencyId
) {
  const amount = toNumber(value);
  const path = conversionPath(product, document, companyCurrencyId);

  if (path === 'same') {
    return amount;
  }

  let converted = amount * productExchangeRate(product, companyCurrencyId);

  if (path === 'via_company') {
    const documentRate = toNumber(document.exchangeRate);

    converted *= documentRate > 0 ? documentRate : 1;
  }

  return typeof document.precision === 'number'
    ? roundToPrecision(converted, document.precision)
    : converted;
}

/**
 * Line amounts of a product added to a document: sale price -> line `cost`, purchase price ->
 * line `product_cost`. Purchase orders keep the upstream rule for the line price: the purchase
 * price when it is set, otherwise the sale price.
 */
export function productLineAmounts(
  product: ProductCurrencyFields,
  document: DocumentCurrency,
  companyCurrencyId: CurrencyId,
  isPurchaseOrder = false
): ProductLineAmounts {
  const linePrice =
    isPurchaseOrder && toNumber(product.cost) > 0
      ? product.cost
      : product.price;

  const documentRate = toNumber(document.exchangeRate);

  return {
    cost: convertProductAmount(linePrice, product, document, companyCurrencyId),
    product_cost: convertProductAmount(
      product.cost,
      product,
      document,
      companyCurrencyId
    ),
    missingDocumentRate:
      conversionPath(product, document, companyCurrencyId) === 'via_company' &&
      (documentRate <= 0 || documentRate === 1),
  };
}
