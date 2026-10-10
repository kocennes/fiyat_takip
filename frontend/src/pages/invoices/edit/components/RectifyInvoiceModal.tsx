/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { MdOutlineWarning } from 'react-icons/md';
import { Button } from '$app/components/forms';
import { InputField } from '$app/components/forms/InputField';
import { Icon } from '$app/components/icons/Icon';
import { Modal } from '$app/components/Modal';

interface Props {
  visible: boolean;
  onClose: () => void;
  onConfirm: (reason: string) => void;
}

export function RectifyInvoiceModal({ visible, onClose, onConfirm }: Props) {
  const [t] = useTranslation();
  const [rectificationReason, setRectificationReason] = useState('');

  const handleConfirm = () => {
    onConfirm(rectificationReason);
    setRectificationReason('');
  };

  const handleClose = () => {
    onClose();
    setRectificationReason('');
  };

  return (
    <Modal
      title={t('bis_rectify_invoice')}
      visible={visible}
      onClose={handleClose}
    >
      <div className="flex items-center">
        <Icon element={MdOutlineWarning} color="orange" size={48} />
        <span className="font-medium text-sm ml-2 text-orange-500">
          {t('bis_verifactu_rectify_warning')}
        </span>
      </div>
      <InputField
        label={t('bis_rectification_reason')}
        value={rectificationReason}
        changeOverride={true}
        onValueChange={(value: string) => setRectificationReason(value)}
        required
      />
      <div className="flex justify-end space-x-2 mt-4">
        <Button type="secondary" onClick={handleClose}>
          {t('cancel')}
        </Button>
        <Button onClick={handleConfirm} disabled={!rectificationReason.trim()}>
          {t('confirm')}
        </Button>
      </div>
    </Modal>
  );
}
