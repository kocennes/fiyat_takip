/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useEffect, useState } from 'react';
import { Country } from '$app/common/interfaces/country';
import { useStaticsQuery } from '$app/common/queries/statics';

const TURKEY_ID = '792';

// Turkey first, the rest in Turkish alphabetical order.
function sortCountries(countries: Country[]): Country[] {
  return [...countries].sort((a, b) => {
    const aTurkey = String(a.id) === TURKEY_ID;
    const bTurkey = String(b.id) === TURKEY_ID;

    if (aTurkey !== bTurkey) {
      return aTurkey ? -1 : 1;
    }

    return a.name.localeCompare(b.name, 'tr');
  });
}

export function useCountries(): Country[] {
  const { data: statics } = useStaticsQuery();
  const [countries, setCountries] = useState<Country[]>([]);

  useEffect(() => {
    if (statics?.countries) {
      setCountries(sortCountries(statics.countries));
    }
  }, [statics]);

  return countries;
}
