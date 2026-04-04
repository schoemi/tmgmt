/**
 * Widget Registry
 *
 * Manages registration and retrieval of dashboard widgets.
 * Supports ordering, permission-based visibility, and duplicate detection.
 */

/**
 * Creates a new widget registry instance.
 * @returns {{ register: Function, getAll: Function, getVisible: Function }}
 */
export function createWidgetRegistry() {
    const widgets = []

    /**
     * Register a widget configuration.
     * Required fields: id, label, icon, component
     *
     * @param {Object} config - Widget configuration object
     * @param {string} config.id - Unique widget identifier
     * @param {string} config.label - Navigation label
     * @param {string} config.icon - FontAwesome icon class
     * @param {Object|Function} config.component - Vue component or lazy import function
     * @param {number} [config.order=0] - Sort order in navigation
     * @param {string[]} [config.permissions=[]] - Required WP capabilities
     */
    function register(config) {
        const required = ['id', 'label', 'icon', 'component']
        for (const field of required) {
            if (!config || config[field] === undefined || config[field] === null || config[field] === '') {
                console.error(`[widgetRegistry] Widget registration failed: missing required field "${field}"`, config)
                return
            }
        }

        if (widgets.some(w => w.id === config.id)) {
            console.error(`[widgetRegistry] Widget with id "${config.id}" is already registered. Duplicate registration rejected.`)
            return
        }

        widgets.push({
            order: 0,
            permissions: [],
            ...config,
        })
    }

    /**
     * Returns all registered widgets sorted ascending by order.
     * @returns {Object[]}
     */
    function getAll() {
        return [...widgets].sort((a, b) => a.order - b.order)
    }

    /**
     * Returns widgets visible to the given user capabilities.
     * A widget is visible if:
     *   - it has no permissions array, OR
     *   - the permissions array is empty, OR
     *   - ALL permissions in the array are truthy in userCapabilities
     *
     * @param {Object} userCapabilities - Map of capability slug to boolean
     * @returns {Object[]}
     */
    function getVisible(userCapabilities = {}) {
        return getAll().filter(widget => {
            if (!widget.permissions || widget.permissions.length === 0) {
                return true
            }
            return widget.permissions.every(cap => Object.prototype.hasOwnProperty.call(userCapabilities, cap) && !!userCapabilities[cap])
        })
    }

    return { register, getAll, getVisible }
}

/** Default singleton instance */
export default createWidgetRegistry()
