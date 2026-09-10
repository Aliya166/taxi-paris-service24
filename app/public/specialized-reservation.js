(() => {
  const form = document.getElementById("specializedReservationForm");

  if (!form) {
    return;
  }

  const startInput = document.getElementById("specializedStart");
  const endInput = document.getElementById("specializedEnd");
  const startSuggestions = document.getElementById(
    "specializedStartSuggestions"
  );
  const endSuggestions = document.getElementById(
    "specializedEndSuggestions"
  );
  const nowButton = document.getElementById("specializedNowBtn");
  const laterButton = document.getElementById("specializedLaterBtn");
  const dateTimeBox = document.getElementById("specializedDateTimeBox");
  const dateInput = document.getElementById("specializedDate");
  const timeInput = document.getElementById("specializedTime");
  const submitButton = form.querySelector('button[type="submit"]');
  const quickButtons = document.querySelectorAll("[data-quick-address]");
  const vehicleInputs = form.querySelectorAll('input[name="vehicle"]');
  const vehicleOptions = form.querySelectorAll(".service-vehicle");
  const childSeatInput = document.getElementById("childSeat");
  const childSeatButton = document.querySelector("[data-child-seat-toggle]");
  const suggestionsCache = new Map();

  function normalizeAddress(value) {
    return value.trim().toLocaleLowerCase("fr-FR");
  }

  async function searchSuggestions(query, signal) {
    const normalizedQuery = normalizeAddress(query);

    if (normalizedQuery.length < 4) {
      return [];
    }

    if (suggestionsCache.has(normalizedQuery)) {
      return suggestionsCache.get(normalizedQuery);
    }

    const response = await fetch(
      `/api/address-suggestions?q=${encodeURIComponent(query)}&mode=autocomplete`,
      {
        headers: { Accept: "application/json" },
        signal,
      }
    );

    if (!response.ok) {
      return [];
    }

    const data = await response.json();
    const features = data.features || [];

    suggestionsCache.set(normalizedQuery, features);

    return features;
  }

  function setupAutocomplete(input, suggestionsBox) {
    let timer = null;
    let controller = null;

    input.addEventListener("input", () => {
      const query = input.value.trim();

      suggestionsBox.innerHTML = "";
      window.clearTimeout(timer);

      if (controller) {
        controller.abort();
      }

      if (query.length < 4) {
        return;
      }

      timer = window.setTimeout(async () => {
        controller = new AbortController();

        try {
          const suggestions = await searchSuggestions(
            query,
            controller.signal
          );

          if (input.value.trim() !== query) {
            return;
          }

          suggestionsBox.innerHTML = "";

          suggestions.forEach((item) => {
            const option = document.createElement("button");

            option.type = "button";
            option.className = "suggestion-item";
            option.textContent = item.properties.label;

            option.addEventListener("click", () => {
              input.value = item.properties.label;
              suggestionsBox.innerHTML = "";
            });

            suggestionsBox.appendChild(option);
          });
        } catch (error) {
          if (error.name !== "AbortError") {
            console.error("Address suggestions error:", error);
          }
        }
      }, 450);
    });
  }

  setupAutocomplete(startInput, startSuggestions);
  setupAutocomplete(endInput, endSuggestions);

  quickButtons.forEach((button) => {
    button.addEventListener("click", () => {
      endInput.value = button.dataset.quickAddress;
      endSuggestions.innerHTML = "";

      quickButtons.forEach((item) => item.classList.remove("is-active"));
      button.classList.add("is-active");

      endInput.focus();
    });
  });

  function chooseNow() {
    nowButton.classList.add("active");
    laterButton.classList.remove("active");
    dateTimeBox.classList.remove("is-visible");
    dateInput.required = false;
    timeInput.required = false;
    dateInput.value = "";
    timeInput.value = "";
  }

  function chooseLater() {
    laterButton.classList.add("active");
    nowButton.classList.remove("active");
    dateTimeBox.classList.add("is-visible");
    dateInput.required = true;
    timeInput.required = true;

    const now = new Date();
    const localDate = new Date(
      now.getTime() - now.getTimezoneOffset() * 60000
    );

    dateInput.min = localDate.toISOString().split("T")[0];
  }

  nowButton.addEventListener("click", chooseNow);
  laterButton.addEventListener("click", chooseLater);

  vehicleInputs.forEach((input) => {
    input.addEventListener("change", () => {
      vehicleOptions.forEach((option) => {
        option.classList.remove("active");
      });

      input.closest(".service-vehicle").classList.add("active");
    });
  });

  if (childSeatButton && childSeatInput) {
    childSeatButton.addEventListener("click", () => {
      const selected = childSeatInput.value !== "1";

      childSeatInput.value = selected ? "1" : "0";
      childSeatButton.classList.toggle("is-active", selected);
      childSeatButton.setAttribute("aria-pressed", selected ? "true" : "false");

      const state = childSeatButton.querySelector("[data-child-seat-state]");

      if (state) {
        state.textContent = selected ? "Ajouté" : "Ajouter";
      }
    });
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const originalText = submitButton.innerHTML;

    submitButton.disabled = true;
    submitButton.innerHTML = "<span>Envoi en cours…</span>";

    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: { Accept: "application/json" },
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(
          result.message || "La réservation n’a pas pu être enregistrée."
        );
      }

      sessionStorage.setItem("reservationReference", result.reference);
      window.location.href = result.redirect || "/confirmation.html";
    } catch (error) {
      window.alert(
        error.message || "Une erreur est survenue. Veuillez réessayer."
      );

      submitButton.disabled = false;
      submitButton.innerHTML = originalText;
    }
  });

  chooseNow();
})();
