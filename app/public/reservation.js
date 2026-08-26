// 1. ТВОЙ КЛЮЧ OpenRouteService


// 2. Карта Leaflet
const map = L.map("map").setView([48.8566, 2.3522], 11);

L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  attribution: "© OpenStreetMap"
}).addTo(map);

let routeLayer = null;
let startMarker = null;
let endMarker = null;
let currentDistanceKm = 0;
let currentDurationMin = 0;

// 3. Элементы страницы
const startInput = document.getElementById("start");
const endInput = document.getElementById("end");
const calculateBtn = document.getElementById("calculateRoute");

const startSuggestions =
  document.getElementById("startSuggestions");

const endSuggestions =
  document.getElementById("endSuggestions");


const suggestionsCache = new Map();
const coordinatesCache = new Map();

function normalizeAddress(value) {
  return value.trim().toLocaleLowerCase("fr-FR");
}

async function searchAddressSuggestions(query, signal) {
  const normalizedQuery = normalizeAddress(query);

  if (normalizedQuery.length < 4) {
    return [];
  }

  if (suggestionsCache.has(normalizedQuery)) {
    return suggestionsCache.get(normalizedQuery);
  }

  try {
    const response = await fetch(
      `/api/address-suggestions?q=${encodeURIComponent(query)}`,
      {
        headers: {
          Accept: "application/json",
        },
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
  } catch (error) {
    if (error.name !== "AbortError") {
      console.error("Address suggestions error:", error);
    }

    return [];
  }
}

function setupAutocomplete(input, suggestionsBox) {
  let debounceTimer = null;
  let requestController = null;

  input.addEventListener("input", () => {
    const query = input.value.trim();

    suggestionsBox.innerHTML = "";

    clearTimeout(debounceTimer);

    if (requestController !== null) {
      requestController.abort();
      requestController = null;
    }

    if (query.length < 4) {
      return;
    }

    debounceTimer = setTimeout(async () => {
      requestController = new AbortController();

      const suggestions = await searchAddressSuggestions(
        query,
        requestController.signal
      );

      if (
        requestController.signal.aborted
        || input.value.trim() !== query
      ) {
        return;
      }

      suggestionsBox.innerHTML = "";

      suggestions.forEach((item) => {
        const div = document.createElement("div");

        div.className = "suggestion-item";
        div.textContent = item.properties.label;

        div.addEventListener("click", () => {
          const selectedAddress = item.properties.label;

          input.value = selectedAddress;
          suggestionsBox.innerHTML = "";

          coordinatesCache.set(
            normalizeAddress(selectedAddress),
            item.geometry.coordinates
          );
        });

        suggestionsBox.appendChild(div);
      });
    }, 500);
  });
}

setupAutocomplete(
  startInput,
  startSuggestions
);

setupAutocomplete(
  endInput,
  endSuggestions
);

const distanceText = document.getElementById("distanceText");
const durationText = document.getElementById("durationText");
const priceText = document.getElementById("priceText");

const vehicleInputs = document.querySelectorAll('input[name="vehicle"]');
const vehicleOptions = document.querySelectorAll(".vehicle-option");

// 4. Поиск координат по адресу
async function getCoordinates(address) {
  const normalizedAddress = normalizeAddress(address);

  if (coordinatesCache.has(normalizedAddress)) {
    return coordinatesCache.get(normalizedAddress);
  }

  const response = await fetch(
    `/api/address-suggestions?q=${encodeURIComponent(address)}`,
    {
      headers: {
        Accept: "application/json",
      },
    }
  );

  if (!response.ok) {
    throw new Error("Service de recherche indisponible");
  }

  const data = await response.json();

  if (!data.features || data.features.length === 0) {
    throw new Error("Adresse introuvable");
  }

  const coordinates = data.features[0].geometry.coordinates;

  coordinatesCache.set(normalizedAddress, coordinates);

  return coordinates;
}

// 5. Получить тариф выбранной машины
function getSelectedRate() {
  const selectedVehicle = document.querySelector('input[name="vehicle"]:checked');
  return Number(selectedVehicle.dataset.rate);
}

// 6. Обновить цену
function updatePrice() {

  const rate = getSelectedRate();

  const minimumFinalPrice = 39;

  const reservationFee = 15;
  const pricePerMinute = 0.30;

  const ridePrice = currentDistanceKm * rate;
  const durationPrice = currentDurationMin * pricePerMinute;

  const finalPrice = Math.max(
    ridePrice + durationPrice + reservationFee,
    minimumFinalPrice
  );

  priceText.textContent =
    `${finalPrice.toFixed(2).replace(".", ",")} €`;
}

function getFixedPrice(startAddress, endAddress) {
  const start = startAddress.toLowerCase();
  const end = endAddress.toLowerCase();

  const isParis75 =
    start.includes("paris") ||
    start.includes("750");

  if (isParis75 && end.includes("orly")) {
    return 59;
  }

  if (
    isParis75 &&
    (
      end.includes("charles de gaulle") ||
      end.includes("cdg") ||
      end.includes("roissy")
    )
  ) {
    return 69;
  }

  if (isParis75 && end.includes("beauvais")) {
    return 150;
  }

  if (
    isParis75 &&
    (
      end.includes("disney") ||
      end.includes("disneyland") ||
      end.includes("marne-la-vallée") ||
      end.includes("marne la vallée")
    )
  ) {
    return 85;
  }

  if (
    start.includes("orly") &&
    (
      end.includes("charles de gaulle") ||
      end.includes("cdg") ||
      end.includes("roissy")
    )
  ) {
    return 110;
  }

  return null;
}

// 7. Расчёт маршрута
async function calculateRoute() {
  try {
    const startAddress = startInput.value.trim();
    const endAddress = endInput.value.trim();

    const fixedPrice = getFixedPrice(startAddress, endAddress);

    const allowed = [
      "paris",

      // Seine-Saint-Denis (93)
      "saint-denis",
      "bobigny",
      "montreuil",
      "aubervilliers",
      "pantin",
      "noisy",
      "aulnay",
      "drancy",
      "bondy",
      "livry",
      "rosny",

      // Val-de-Marne (94)
      "creteil",
      "vitry",
      "ivry",
      "champigny",
      "choisy",
      "villejuif",
      "nogent",
      "vincennes",
      "fontenay",
      "joinville",

      // Hauts-de-Seine (92)
      "nanterre",
      "boulogne",
      "courbevoie",
      "colombes",
      "asnieres",
      "levallois",
      "puteaux",
      "clamart",
      "meudon",
      "issy",

      // Yvelines (78)
      "versailles",
      "saint-germain",
      "mantes",
      "poissy",
      "sartrouville",
      "chatou",
      "houilles",

      // Essonne (91)
      "evry",
      "corbeil",
      "massy",
      "palaiseau",
      "savigny",
      "viry",
      "ris-orangis",
      "grigny",
      "etampes",

      // Seine-et-Marne (77)
      "melun",
      "chelles",
      "meaux",
      "pontault",
      "torcy",
      "serris",
      "fontainebleau",
      "coulommiers",

      // Val-d'Oise (95)
      "pontoise",
      "cergy",
      "argenteuil",
      "sarcelles",
      "franconville",
      "garges",
      "bezons",
      "ermont",
      "noisy-le-grand",
      "montreuil",
      "saint-denis",
      "creteil",
      "champigny-sur-marne",
      "bry-sur-marne",
      "fontenay-sous-bois",
      "neuilly-plaisance",
      "bondy",
      "aulnay-sous-bois",
      "issy-les-moulineaux",
      "boulogne-billancourt",
      "vitry-sur-seine",
      "saint-maurice",
      "orly",
      "roissy",
      "cdg",
      "beauvais",
      "disneyland",
      "gare de lyon"
    ];

    const isValid =
      allowed.some(city =>
        startAddress.toLowerCase().includes(city)
      ) &&
      allowed.some(city =>
        endAddress.toLowerCase().includes(city)
      );

    if (!isValid) {
      alert("Veuillez choisir une adresse en Île-de-France");
      return;
    }

    if (!startAddress || !endAddress) {
      alert("Veuillez renseigner les deux adresses.");
      return;
    }

    calculateBtn.textContent = "Calcul en cours...";
    calculateBtn.disabled = true;

    const startCoords = await getCoordinates(startAddress);
    const endCoords = await getCoordinates(endAddress);

    const routeResponse = await fetch("/api/route", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        coordinates: [startCoords, endCoords],
      }),
    });

    if (!routeResponse.ok) {
      throw new Error("Service de calcul indisponible");
    }

    const routeData = await routeResponse.json();

    if (!routeData.features || routeData.features.length === 0) {
      throw new Error("Route introuvable");
    }

    const route = routeData.features[0];
    const summary = route.properties.summary;

    const distanceKm = summary.distance / 1000;
    const durationMin = summary.duration / 60;

    currentDistanceKm = distanceKm;
    currentDurationMin = durationMin;

    distanceText.textContent = `${distanceKm.toFixed(1)} km`;
    durationText.textContent = `${Math.round(durationMin)} min`;

    if (fixedPrice !== null) {
      priceText.textContent = `${fixedPrice.toFixed(2).replace(".", ",")} €`;
    } else {
      updatePrice();
    }

    if (routeLayer) map.removeLayer(routeLayer);
    if (startMarker) map.removeLayer(startMarker);
    if (endMarker) map.removeLayer(endMarker);

    routeLayer = L.geoJSON(route, {
      style: {
        color: "#d4a63c",
        weight: 5
      }
    }).addTo(map);

    startMarker = L.marker([startCoords[1], startCoords[0]]).addTo(map);
    endMarker = L.marker([endCoords[1], endCoords[0]]).addTo(map);

    map.fitBounds(routeLayer.getBounds(), {
      padding: [30, 30]
    });

  } catch (error) {
    console.error(error);
    alert("Erreur : vérifiez les adresses saisies.");
  } finally {
    calculateBtn.textContent = "Calculer le trajet";
    calculateBtn.disabled = false;
  }
}

// 8. Клик по кнопке
calculateBtn.addEventListener("click", calculateRoute);

// 9. Пересчёт цены при выборе машины
vehicleInputs.forEach((input) => {
  input.addEventListener("change", () => {
    vehicleOptions.forEach((option) => option.classList.remove("active"));
    input.closest(".vehicle-option").classList.add("active");
    updatePrice();
  });
});

// 10. Отправка формы
const reservationForm = document.getElementById("reservationForm");

reservationForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  document.getElementById("hiddenStart").value = startInput.value;
  document.getElementById("hiddenEnd").value = endInput.value;
  document.getElementById("hiddenDistance").value = distanceText.textContent;
  document.getElementById("hiddenDuration").value = durationText.textContent;
  document.getElementById("hiddenPrice").value = priceText.textContent;

  try {
    const response = await fetch(reservationForm.action, {
      method: "POST",
      body: new FormData(reservationForm),
      headers: {
        Accept: "application/json"
      }
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(
        result.message || "La réservation n’a pas pu être enregistrée."
      );
    }

    sessionStorage.setItem(
      "reservationReference",
      result.reference
    );

    window.location.href =
      result.redirect || "/confirmation.html";
  } catch (error) {
    alert(
      error.message ||
      "Une erreur est survenue. Veuillez réessayer."
    );
  }
});