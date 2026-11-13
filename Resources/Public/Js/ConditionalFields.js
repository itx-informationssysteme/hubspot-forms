(() => {
    const baseName = (el) => {
        const m = el.name.match(/\[([^\]]+)\]$/);
        return m ? m[1] : el.name;
    };

    const getValues = (form, name) => {
        const values = [];
        form.querySelectorAll('input, select, textarea').forEach((el) => {
            if (!el.name || el.disabled) return;
            const n = baseName(el);
            const isGroupMember = n.startsWith(name + '/');
            if (n !== name && !isGroupMember) return;

            if (el.type === 'checkbox') {
                if (el.checked) values.push(isGroupMember ? n.slice(name.length + 1) : 'true');
            } else if (el.type === 'radio') {
                if (el.checked) values.push(el.value);
            } else if (el.value !== '') {
                values.push(el.value);
            }
        });
        return values;
    };

    const isMet = (operator, expected, submitted) => {
        const has = (v) => submitted.includes(v);
        const sameSet = expected.every(has) && submitted.every((s) => expected.includes(s));
        switch (operator) {
            case 'set_any':      return expected.some(has);
            case 'set_all':      return expected.length > 0 && expected.every(has);
            case 'set_not_any':  return !expected.some(has);
            case 'set_not_all':  return !expected.every(has);
            case 'set_eq':       return submitted.length > 0 && sameSet;
            case 'set_neq':      return !(submitted.length > 0 && sameSet);
            case 'is_not_empty': return submitted.length > 0;
            default:
                console.warn('ConditionalFields: unsupported operator', operator);
                return true;
        }
    }

    const init = (form) => {
        const wrappers = Array.from(form.querySelectorAll('[data-dependent-on]'));
        if (!wrappers.length) return;

        const conditions = new Map();
        wrappers.forEach((wrapper) => {
            try {
                conditions.set(wrapper, JSON.parse(wrapper.dataset.values || '[]').map(String));
            } catch (e) {
                console.warn('ConditionalFields: invalid data-values', wrapper, e);
                conditions.set(wrapper, []);
            }
        });

        const update = () => {
            wrappers.forEach((wrapper) => {
                const parentHidden = wrapper.parentElement.closest('[data-dependent-on][hidden]') !== null;
                const submitted = getValues(form, wrapper.dataset.dependentOn);
                const show = !parentHidden && isMet(wrapper.dataset.operator, conditions.get(wrapper), submitted);

                wrapper.hidden = !show;
                wrapper.querySelectorAll('input, select, textarea').forEach((el) => {
                    el.disabled = !show;
                    if (!show && (el.type === 'checkbox' || el.type === 'radio')) el.checked = false;
                });
            });
        };

        if (window.jQuery) {
            window.jQuery(form).on('change', update);
        } else {
            form.addEventListener('change', update);
        }
        form.addEventListener('reset', () => setTimeout(update));
        window.addEventListener('pageshow', update);

        update();
    };

    const ready = () => document.querySelectorAll('form').forEach(init);
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', ready) : ready();
})();
