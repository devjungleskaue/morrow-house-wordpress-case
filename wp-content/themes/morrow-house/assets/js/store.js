document.documentElement.classList.add("js");

document.addEventListener("DOMContentLoaded", () => {
  const button = document.querySelector(".menu-toggle");
  const menu = document.querySelector("#primary-menu");
  if (button instanceof HTMLButtonElement && menu instanceof HTMLElement) {
    button.addEventListener("click", () => {
      const open = button.getAttribute("aria-expanded") === "true";
      button.setAttribute("aria-expanded", String(!open));
      menu.toggleAttribute("data-open", !open);
    });
  }

  for (const eventName of ["wc-blocks_added_to_cart", "wc-blocks_removed_from_cart"]) {
    document.body.addEventListener(eventName, () => {
      window.jQuery?.(document.body).trigger("wc_fragment_refresh");
    });
  }
});
