const body = document.querySelector("body");
const observer = new MutationObserver(function(mutationObject) {
    for(const event of mutationObject) {
        const node = event.target;
        const nodeName = node.nodeName;
        const classList = node.classList;

        if (nodeName === "SELECT" && classList.contains("form-select")) {
            const parent = node.parentElement;
            const childOptions = node.getElementsByTagName("option").length;

            if (childOptions > 1) {
                parent.classList.remove("loading");
                parent.classList.add("loaded");
                node.removeAttribute('disabled');
            }
        } else {
            const newSelects = document.querySelectorAll("select.form-select:not(.touched)");

            for(const newSelect of newSelects)
            {
                newSelect.classList.add("touched");
                const parent = newSelect.parentElement;
                const childOptions = newSelect.getElementsByTagName("option").length;

                if (childOptions > 1) {
                    parent.classList.add("loaded");
                    newSelect.removeAttribute('disabled');
                } else {
                    parent.classList.add("loading");
                    newSelect.setAttribute('disabled', 'disabled');
                }
            }
        }
    }
});
observer.observe(body, {subtree: true, childList: true});
