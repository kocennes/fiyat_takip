/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useReactSettingsField } from './hooks/useReactSettings';

// export const $1 = {
//   name: 'invoiceninja.dark',
//   $0: 'dark',
//   $1: '#182433',
//   $2: '#151f2c',
//   $3: '#ffffff',
//   $4: '#1f2e41',
//   $5: '#1f2e41',
//   $6: '#151f2c',
//   $7: '#151f2c',
//   $8: '#1f2e41',
//   $9: '#ffffff',
// };

export const darkColorScheme = {
  name: 'bisavunma.dark',
  $0: 'dark',
  $1: '#0d1c2b',
  $2: '#091624',
  $3: '#e8f2f8',
  $4: '#21435b',
  $5: '#28516c',
  $6: '#07111c',
  $7: '#122a3c',
  $8: '#123f5a',
  $9: '#f7fcff',
  $10: 0.87, // High emphasis text
  $11: 0.6, // Medium emphasis text
  $12: 0.38, // Disabled text
  $13: '#193850',
  $14: '#06111d',
  $15: '#173149',
  $16: '#80a6bc',
  $17: '#9bb7c7',
  $18: '#2aaee5',
  $19: '#21435b',
  $20: '#153149',
  $21: '#21435b',
  $22: '#91adbd',
  $23: '#07131f',
  $24: '#28516c',
  $25: '#102a3d',
};

export const lightColorScheme = {
  name: 'bisavunma.light',
  $0: 'light',
  $1: '#ffffff',
  $2: '#f3f7fa',
  $3: '#10283b',
  $4: '#d6e2eb',
  $5: '#bfd0dc',
  $6: '#0a1a29',
  $7: '#edf5fa',
  $8: '#0c5073',
  $9: '#ffffff',
  $10: 1, // High emphasis text
  $11: 0.8, // Secondary text opacity
  $12: 0.5, // Disabled text opacity
  $13: '#e1eff7',
  $14: '#071726',
  $15: '#dcebf3',
  $16: '#58758a',
  $17: '#6d8898',
  $18: '#0d6f9f',
  $19: '#c9dbe6',
  $20: '#e9f3f8',
  $21: '#d6e2eb',
  $22: '#58758a',
  $23: '#edf3f7',
  $24: '#c8d8e3',
  $25: '#e5f0f6',
};

export function useColorScheme() {
  const darkMode = useReactSettingsField('dark_mode');

  return darkMode ? darkColorScheme : lightColorScheme;
}
