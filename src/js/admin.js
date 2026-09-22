/** @jsxRuntime classic */
/** @jsx createElement */
import { createElement, createRoot, useEffect, useMemo, useState } from '@wordpress/element';
import { Button, Card, CardBody, ColorPalette, Notice, Popover, SelectControl, SlotFillProvider, TextControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { createQr, normalizeUrl } from './qr-code';
import { colorContrast, normalizeColor, resolveColors } from './qr-settings';

function errorMessage(error) {
    if (error.code === 'invalidUrl') { return __('Enter a valid HTTP or HTTPS URL.', 'rrze-qr'); }
    if (error.code === 'tooLong') { return __('This URL is too long for a QR code. Use a shorter URL (maximum 2,953 encoded characters).', 'rrze-qr'); }
    if (error.code === 'contrast') { return __('Foreground and background must be different colors.', 'rrze-qr'); }
    if (error.code === 'invalidColor') { return __('Choose a valid foreground and background color.', 'rrze-qr'); }
    return __('The QR code could not be generated. Reload the page and try again.', 'rrze-qr');
}

function imageDimensions(width) {
    /* translators: %1$d: width and height of the square image in pixels. */
    return sprintf(__('PNG · %1$d × %1$d px', 'rrze-qr'), width);
}

function Workspace({ config }) {
    const [url, setUrl] = useState(config.initialUrl);
    const [defaults, setDefaults] = useState(config.defaults);
    const [settings, setSettings] = useState(config.defaults);
    const [solidBackground, setSolidBackground] = useState(config.defaults.background === 'transparent' ? '#ffffff' : config.defaults.background);
    const [surface, setSurface] = useState('checkerboard');
    const [image, setImage] = useState(null);
    const [generationError, setGenerationError] = useState(null);
    const [saving, setSaving] = useState(false);
    const [saveNotice, setSaveNotice] = useState(null);
    const { foreground, background, size } = settings;
    const sourceMatches = config.context && url.trim() === config.context.url;
    /* translators: %s: post or page title. */
    const sourceTitle = sourceMatches ? sprintf(__('QR code for %s', 'rrze-qr'), config.context.title) : null;
    const key = JSON.stringify([url, foreground, background, size]);
    const dirty = foreground !== defaults.foreground || background !== defaults.background || size !== defaults.size;
    const colors = useMemo(() => {
        try { return { value: resolveColors(foreground, background) }; }
        catch (error) { return { error: errorMessage(error) }; }
    }, [foreground, background]);
    const contrast = colors.value ? colorContrast(colors.value) : null;
    const input = useMemo(() => {
        if (!url.trim()) { return {}; }
        try { return { value: normalizeUrl(url.trim()) }; }
        catch (error) { return { error: errorMessage(error) }; }
    }, [url]);

    useEffect(() => {
        if (!input.value || !colors.value) { return; }
        const timer = setTimeout(() => {
            try {
                const qr = createQr(QRious, { value: input.value, size, ...colors.value });
                setImage({ key, src: qr.toDataURL(), width: qr.size });
                setGenerationError(null);
            } catch (error) {
                setGenerationError({ key, message: errorMessage(error) });
            }
        }, 150);
        return () => clearTimeout(timer);
    }, [key, input, colors, size]);

    const currentImage = image?.key === key ? image : null;
    const error = input.error || colors.error || (generationError?.key === key ? generationError.message : null);
    function changeSetting(name, value) {
        if (saving) { return; }
        setSettings(current => ({ ...current, [name]: value }));
        setSaveNotice(null);
    }
    function changeColor(name, value) {
        const color = normalizeColor(value);
        if (!color || saving) { return; }
        if (name === 'background') { setSolidBackground(color); }
        changeSetting(name, color);
    }
    async function saveDefaults() {
        if (saving || colors.error || !config.canSaveDefaults) { return; }
        setSaving(true);
        setSaveNotice(null);
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(config.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: new URLSearchParams({ action: 'rrze_qr_save_defaults', nonce: config.nonce, foreground, background, size }),
                signal: controller.signal
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(typeof payload.data === 'string' ? payload.data : __('The defaults could not be saved. Reload the page and try again.', 'rrze-qr'));
            }
            setDefaults(payload.data);
            setSettings(payload.data);
            setSaveNotice({ status: 'success', text: __('Site defaults saved. New QR codes will use these settings.', 'rrze-qr') });
        } catch (failure) {
            setSaveNotice({ status: 'error', text: failure.name === 'Error' ? failure.message : __('The defaults could not be saved. Reload the page and try again.', 'rrze-qr') });
        } finally {
            clearTimeout(timer);
            setSaving(false);
        }
    }
    const palette = [
        { name: __('Black', 'rrze-qr'), color: '#000000' },
        { name: __('White', 'rrze-qr'), color: '#ffffff' },
        { name: __('FAU Blue', 'rrze-qr'), color: '#04316a' }
    ];

    return (
        <div className="rrze-qr-layout">
            {config.context && <div className="rrze-qr-source">
                {sourceTitle && <p>{sourceTitle}</p>}
                <a href={config.context.backUrl}>{config.context.backLabel}</a>
            </div>}
            <Card className="rrze-qr-controls">
                <CardBody>
                    <h2>{__('Configure your QR code', 'rrze-qr')}</h2>
                    <TextControl
                        label={__('Destination URL', 'rrze-qr')}
                        type="url" value={url} onChange={setUrl}
                        placeholder="https://example.com/" autoComplete="off" spellCheck={false}
                        help={__('Enter an HTTP or HTTPS URL. The preview updates automatically.', 'rrze-qr')}
                    />
                    <div className="rrze-qr-color-fields">
                        <fieldset className="rrze-qr-color-field" disabled={saving}>
                            <legend>{__('Foreground', 'rrze-qr')}</legend>
                            <ColorPalette aria-label={__('Foreground', 'rrze-qr')} colors={palette} value={foreground} clearable={false} disableCustomColors={false} enableAlpha={false} onChange={value => changeColor('foreground', value)} />
                        </fieldset>
                        <fieldset className="rrze-qr-color-field" disabled={saving}>
                            <legend>{__('Background', 'rrze-qr')}</legend>
                            {background !== 'transparent' && <ColorPalette aria-label={__('Background', 'rrze-qr')} colors={palette} value={background} clearable={false} disableCustomColors={false} enableAlpha={false} onChange={value => changeColor('background', value)} />}
                            <ToggleControl label={__('Transparent background', 'rrze-qr')} checked={background === 'transparent'} disabled={saving} onChange={transparent => {
                                if (saving) { return; }
                                if (transparent) { setSolidBackground(background); }
                                changeSetting('background', transparent ? 'transparent' : solidBackground);
                            }} />
                        </fieldset>
                    </div>
                    <SelectControl
                        label={__('Export size', 'rrze-qr')} value={String(size)} disabled={saving}
                        options={[
                            { label: __('Small — approximately 300 px', 'rrze-qr'), value: '300' },
                            { label: __('Medium — approximately 600 px', 'rrze-qr'), value: '600' },
                            { label: __('Large — approximately 1200 px', 'rrze-qr'), value: '1200' }
                        ]}
                        onChange={value => changeSetting('size', Number(value))}
                        help={__('The exact dimensions adapt to keep the QR pattern sharp and include a clear margin.', 'rrze-qr')}
                    />
                    <div className="rrze-qr-defaults">
                        <p>{__('These controls change your current download. Site defaults are saved separately.', 'rrze-qr')}</p>
                        <div className="rrze-qr-actions">
                            <Button variant="secondary" disabled={!dirty || saving} onClick={() => { setSettings(defaults); setSolidBackground(defaults.background === 'transparent' ? '#ffffff' : defaults.background); setSaveNotice(null); }}>{__('Reset to defaults', 'rrze-qr')}</Button>
                            {config.canSaveDefaults && <Button variant="secondary" disabled={!dirty || Boolean(colors.error) || saving} isBusy={saving} onClick={saveDefaults}>{saving ? __('Saving…', 'rrze-qr') : __('Save as defaults', 'rrze-qr')}</Button>}
                        </div>
                        {saveNotice && <Notice status={saveNotice.status} isDismissible={false}>{saveNotice.text}</Notice>}
                    </div>
                </CardBody>
            </Card>
            <Card className="rrze-qr-preview">
                <CardBody>
                    <h2>{__('Preview and download', 'rrze-qr')}</h2>
                    {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
                    <div className={'rrze-qr-preview-surface rrze-qr-surface-' + surface} aria-busy={Boolean(input.value && !error && !currentImage)}>
                        {currentImage && !error
                            ? <img src={currentImage.src} width={currentImage.width} height={currentImage.width} alt={__('Generated QR code', 'rrze-qr')} />
                            : <p className="rrze-qr-placeholder">{error ? __('Update the settings to generate a QR code.', 'rrze-qr') : input.value ? __('Generating QR code…', 'rrze-qr') : __('Enter a URL to see your QR code.', 'rrze-qr')}</p>}
                    </div>
                    {background === 'transparent' && <SelectControl
                        label={__('Preview background', 'rrze-qr')} value={surface}
                        options={[{ label: __('Checkerboard', 'rrze-qr'), value: 'checkerboard' }, { label: __('White', 'rrze-qr'), value: 'white' }, { label: __('Black', 'rrze-qr'), value: 'black' }]}
                        onChange={setSurface} help={__('This background is only for the preview; the downloaded PNG stays transparent.', 'rrze-qr')}
                    />}
                    {contrast && contrast.ratio < 3 && <Notice status="warning" isDismissible={false}>{__('These colors have low contrast. Use more contrasting colors and test the code before publishing.', 'rrze-qr')}</Notice>}
                    {(background === 'transparent' || contrast?.inverted) && <Notice status="warning" isDismissible={false}>{__('Test this code on its intended background. Transparent or inverted codes may not work with every scanner.', 'rrze-qr')}</Notice>}
                    <p className="rrze-qr-dimensions" role="status" aria-live="polite">{currentImage && !error
                        ? imageDimensions(currentImage.width) : '\u00a0'}</p>
                    {currentImage && !error
                        ? <Button variant="primary" href={currentImage.src} download={sourceMatches ? config.context.filename : 'qr-code.png'}>{__('Download PNG', 'rrze-qr')}</Button>
                        : <Button variant="primary" disabled>{__('Download PNG', 'rrze-qr')}</Button>}
                </CardBody>
            </Card>
        </div>
    );
}

const container = document.getElementById('rrze-qr-app');
if (container) {
    createRoot(container).render(<SlotFillProvider><Workspace config={rrzeQrAdmin} /><Popover.Slot /></SlotFillProvider>);
}
