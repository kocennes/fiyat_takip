/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useEnabled } from '$app/common/guards/guards/enabled';
import { useTitle } from '$app/common/hooks/useTitle';
import { Activity } from '$app/pages/dashboard/components/Activity';
import { PastDueInvoices } from '$app/pages/dashboard/components/PastDueInvoices';
import { RecentPayments } from '$app/pages/dashboard/components/RecentPayments';
import { Totals } from '$app/pages/dashboard/components/Totals';
import { UpcomingInvoices } from '$app/pages/dashboard/components/UpcomingInvoices';
import { Default } from '../../components/layouts/Default';
import { ModuleBitmask } from '../settings';
import { ExpiredQuotes } from './components/ExpiredQuotes';
import { UpcomingQuotes } from './components/UpcomingQuotes';
import { UpcomingRecurringInvoices } from './components/UpcomingRecurringInvoices';
import { OperationsDeck } from './components/OperationsDeck';

export default function Dashboard() {
  useTitle('dashboard');

  const enabled = useEnabled();

  return (
    <Default title="Operasyon Merkezi" breadcrumbs={[]}>
      <div className="mb-6 flex flex-col gap-1 border-l-4 border-bisavunma-signal pl-4">
        <span className="bisavunma-section-label">Operasyon kontrolü</span>
        <p className="text-sm text-bisavunma-steel dark:text-slate-300">
          Fiyat, teklif ve tedarik akışlarını tek merkezden izleyin.
        </p>
      </div>
      <OperationsDeck />
      <Totals />

      <div className="grid grid-cols-12 gap-8 my-8">
        <div className="col-span-12 xl:col-span-6">
          <Activity />
        </div>

        <div className="col-span-12 xl:col-span-6">
          <RecentPayments />
        </div>

        {enabled(ModuleBitmask.Invoices) && (
          <div className="col-span-12 xl:col-span-6">
            <UpcomingInvoices />
          </div>
        )}

        {enabled(ModuleBitmask.Invoices) && (
          <div className="col-span-12 xl:col-span-6">
            <PastDueInvoices />
          </div>
        )}

        {enabled(ModuleBitmask.Quotes) && (
          <div className="col-span-12 xl:col-span-6">
            <ExpiredQuotes />
          </div>
        )}

        {enabled(ModuleBitmask.Quotes) && (
          <div className="col-span-12 xl:col-span-6">
            <UpcomingQuotes />
          </div>
        )}

        {enabled(ModuleBitmask.RecurringInvoices) && (
          <div className="col-span-12 xl:col-span-6">
            <UpcomingRecurringInvoices />
          </div>
        )}
      </div>
    </Default>
  );
}
