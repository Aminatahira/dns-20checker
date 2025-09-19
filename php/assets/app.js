(function () {
  const tabs = document.getElementById("dnsTabs");
  if (!tabs) return;
  const single = document.getElementById("tab-single");
  const bulk = document.getElementById("tab-bulk");
  tabs.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-tab]");
    if (!btn) return;
    const tab = btn.getAttribute("data-tab");
    tabs
      .querySelectorAll(".nav-link")
      .forEach((el) => el.classList.remove("active"));
    btn.classList.add("active");
    if (tab === "single") {
      single.classList.add("show", "active");
      bulk.classList.remove("show", "active");
    } else {
      bulk.classList.add("show", "active");
      single.classList.remove("show", "active");
    }
  });
})();
