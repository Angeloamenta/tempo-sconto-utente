document.addEventListener("DOMContentLoaded", function () {
  const timerDivs = document.querySelectorAll(".tsu-timer");
  const prezziDivs = document.querySelectorAll(".tsu-prezzi");
  const soloScontoDivs = document.querySelectorAll(".tsu-solo-sconto");
  const originalPriceEls = document.querySelectorAll(".tsu-prezzo-originale");

  const duration = parseInt(document.body.dataset.tsuDurata || "2");
  const version = document.body.dataset.tsuVersion;
  const storedVersion = localStorage.getItem("tsuVersion");

  if (version && storedVersion !== version) {
    localStorage.removeItem("tsuStartTime");
    localStorage.setItem("tsuVersion", version);
  }

  let start = localStorage.getItem("tsuStartTime");
  const shouldStart = Array.from(timerDivs).some(div => div.dataset.start === "true");

  if (!start && shouldStart) {
    start = Date.now();
    localStorage.setItem("tsuStartTime", start);
    document.cookie = "tsu_start_time=" + start + "; path=/; max-age=" + (60 * 60);
  }

  if (!start) return;
  const end = parseInt(start) + 1000 * 60 * duration;

  timerDivs.forEach(div => {
    function update() {
      const now = Date.now();
      const diff = end - now;
      if (diff <= 0) {
        div.innerText = "L’offerta è terminata.";
        return;
      }
      const mins = Math.floor(diff / 60000);
      const secs = Math.floor((diff % 60000) / 1000);
      div.innerText = `Offerta valida ancora per: ${mins}m ${secs}s`;
      setTimeout(update, 1000);
    }
    update();
  });

  function updatePrezzi() {
    const now = Date.now();
    const attivo = now < end;
    prezziDivs.forEach(div => {
      const originale = div.dataset.prezzoOriginale;
      const scontato = div.dataset.prezzoScontato;
      div.querySelector(".tsu-original-price").innerHTML = attivo ? "<del>" + originale + "</del>" : "";
      div.querySelector(".tsu-discounted-price").textContent = attivo ? scontato : originale;
    });

    soloScontoDivs.forEach(div => {
      const el = div.querySelector(".tsu-discounted-price");
      const s = div.dataset.prezzoScontato;
      el.textContent = attivo ? s : "";
      div.style.display = attivo ? "block" : "none";
    });

    originalPriceEls.forEach(el => {
      el.style.display = attivo ? "inline" : "none";
    });
  }

  updatePrezzi();
  setInterval(updatePrezzi, 1000);
});
