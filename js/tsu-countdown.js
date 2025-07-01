document.addEventListener("DOMContentLoaded", function () {
  const timerDivs = document.querySelectorAll(".tsu-timer");
  const prezziDivs = document.querySelectorAll(".tsu-prezzi");
  const soloScontoDivs = document.querySelectorAll(".tsu-solo-sconto");

  const settingsVersion = document.body.dataset.tsuVersion;
  const storedVersion = localStorage.getItem("tsuVersion");

  if (settingsVersion && storedVersion !== settingsVersion) {
    localStorage.removeItem("tsuStartTime");
    localStorage.setItem("tsuVersion", settingsVersion);
  }

  let start = localStorage.getItem("tsuStartTime");
  const shouldStart = Array.from(timerDivs).some(div => div.dataset.start === "true");

  if (!start && shouldStart) {
    start = Date.now();
    localStorage.setItem("tsuStartTime", start);
    document.cookie = "tsu_start_time=" + start + "; path=/; max-age=" + (60 * 60);
  }

  if (!start) return;
  const end = parseInt(start) + 1000 * 60 * parseInt(document.body.dataset.tsuDurata || "2");

  // TIMER VISUAL
  timerDivs.forEach(timerDiv => {
    function updateTimer() {
      const now = Date.now();
      const diff = end - now;
      if (diff <= 0) {
        timerDiv.innerText = "L’offerta è terminata, ma puoi ancora acquistarlo.";
        return;
      }
      const mins = Math.floor(diff / 60000);
      const secs = Math.floor((diff % 60000) / 1000);
      timerDiv.innerText = `Super Offerta Lancio -50% valida ancora per: ${mins}m ${secs}s`;
      setTimeout(updateTimer, 1000);
    }
    updateTimer();
  });

  // PREZZI COMPLETI
  prezziDivs.forEach(prezziDiv => {
    const originale = prezziDiv.dataset.prezzoOriginale;
    const scontato = prezziDiv.dataset.prezzoScontato;
    const originalEl = prezziDiv.querySelector(".tsu-original-price");
    const discountEl = prezziDiv.querySelector(".tsu-discounted-price");

    function updatePrezzo() {
      const now = Date.now();
      if (now < end) {
        originalEl.innerHTML = '<del>' + originale + '</del>';
        discountEl.textContent = scontato;
      } else {
        originalEl.textContent = '';
        discountEl.textContent = originale;
      }
    }

    updatePrezzo();
    setInterval(updatePrezzo, 1000);
  });

  // SOLO PREZZO SCONTATO
  soloScontoDivs.forEach(div => {
    const scontato = div.dataset.prezzoScontato;
    const el = div.querySelector(".tsu-discounted-price");

    function updateSoloSconto() {
      const now = Date.now();
      if (now < end) {
        el.textContent = scontato;
        div.style.display = "block";
      } else {
        el.textContent = "";
        div.style.display = "none";
      }
    }

    updateSoloSconto();
    setInterval(updateSoloSconto, 1000);
  });
});
