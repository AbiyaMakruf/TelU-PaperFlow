import './bootstrap';

document.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-builder-remove]');
    if (removeButton) {
        removeButton.closest('[data-builder-item]')?.remove();
        return;
    }

    const addButton = event.target.closest('[data-builder-add]');
    if (!addButton) return;

    const builder = addButton.closest('[data-builder]');
    const template = builder?.querySelector('[data-builder-template]');
    const list = builder?.querySelector('[data-builder-list]');
    if (!template || !list) return;

    const index = `${Date.now()}${list.children.length}`;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', index).trim();
    list.append(wrapper.firstElementChild);
});
