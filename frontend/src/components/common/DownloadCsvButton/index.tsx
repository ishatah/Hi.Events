import {Button, ButtonProps} from '@mantine/core';
import {IconDownload} from '@tabler/icons-react';
import {t} from '@lingui/macro';
import {buildCsv} from '../../../utilites/csv';

interface DownloadCsvButtonProps extends Omit<ButtonProps, 'onClick'> {
    headers: string[];
    data: (string | number)[][];
    filename?: string;
}

export const DownloadCsvButton = ({
                                      headers,
                                      data,
                                      filename = 'download.csv',
                                      ...buttonProps
                                  }: DownloadCsvButtonProps) => {
    const handleDownloadCSV = () => {
        const blob = new Blob(['﻿' + buildCsv(headers, data)], {type: 'text/csv;charset=utf-8;'});
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    return (
        <Button
            leftSection={<IconDownload size={16}/>}
            onClick={handleDownloadCSV}
            variant="light"
            {...buttonProps}
        >
            {t`Download CSV`}
        </Button>
    );
};
