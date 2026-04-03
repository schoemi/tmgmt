/**
 * TMGMT Contract Template Editor – Gutenberg Sidebar Panel
 *
 * Registriert ein PluginSidebar-Panel mit allen verfügbaren Platzhaltern.
 * Klick auf einen Platzhalter fügt ihn in den aktiven Block ein.
 *
 * Vanilla JS – kein Bundler, kein JSX. Verwendet wp.element.createElement.
 */
(function () {
    'use strict';

    var registerPlugin  = wp.plugins.registerPlugin;
    var PluginSidebar   = wp.editPost.PluginSidebar;
    var Button          = wp.components.Button;
    var el              = wp.element.createElement;
    var dispatch        = wp.data.dispatch;
    var select          = wp.data.select;

    /**
     * Fügt einen Platzhalter in den aktiven Block ein.
     * Bei core/paragraph: Inhalt via updateBlockAttributes erweitern.
     * Sonst: neuen Paragraph-Block via insertBlocks einfügen.
     *
     * @param {string} placeholder  z. B. "[event_date]"
     */
    function insertPlaceholder(placeholder) {
        var block = select('core/block-editor').getSelectedBlock();

        if (block && block.name === 'core/paragraph') {
            dispatch('core/block-editor').updateBlockAttributes(block.clientId, {
                content: (block.attributes.content || '') + placeholder
            });
        } else {
            dispatch('core/block-editor').insertBlocks(
                wp.blocks.createBlock('core/paragraph', { content: placeholder })
            );
        }
    }

    /**
     * Rendert einen einzelnen Platzhalter-Button.
     *
     * @param {string} key    Platzhalter-Key, z. B. "[event_date]"
     * @param {string} label  Lesbarer Name, z. B. "Datum"
     */
    function PlaceholderButton(key, label) {
        return el(
            Button,
            {
                key: key,
                variant: 'tertiary',
                style: {
                    display: 'flex',
                    justifyContent: 'space-between',
                    width: '100%',
                    marginBottom: '4px',
                    textAlign: 'left'
                },
                onClick: function () {
                    insertPlaceholder(key);
                }
            },
            label,
            ' ',
            el('code', { style: { marginLeft: '6px', opacity: 0.7 } }, key)
        );
    }

    /**
     * Sidebar-Panel-Komponente.
     */
    function PlaceholderSidebar() {
        var placeholders = (typeof tmgmtContractEditor !== 'undefined')
            ? tmgmtContractEditor.placeholders
            : {};

        var buttons = Object.keys(placeholders).map(function (key) {
            return PlaceholderButton(key, placeholders[key]);
        });

        return el(
            PluginSidebar,
            {
                name: 'tmgmt-placeholders',
                title: 'Platzhalter'
            },
            el(
                'div',
                { style: { padding: '12px' } },
                buttons
            )
        );
    }

    registerPlugin('tmgmt-contract-placeholders', {
        render: PlaceholderSidebar
    });

}());
