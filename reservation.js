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


async function searchAddressSuggestions(query) {
  if (query.length < 4) return [];

  const url =
    `https://api.openrouteservice.org/geocode/search`+
    `?api_key=${window.API_KEY}` +
    `&text=${encodeURIComponent(query)}` +
    `&boundary.country=FR` +
    `&boundary.rect.min_lon=1.45` +
    `&boundary.rect.min_lat=48.10` +
    `&boundary.rect.max_lon=3.55` +
    `&boundary.rect.max_lat=49.25` +
    `&focus.point.lon=2.3522` +
    `&focus.point.lat=48.8566` +
    `&size=7` +
    `&lang=fr`;

  const response = await fetch(url);
  const data = await response.json();

  return data.features || [];
}


function setupAutocomplete(
input,
suggestionsBox
){

input.addEventListener(
"input",
async()=>{

const query=input.value.trim();

suggestionsBox.innerHTML="";

const suggestions=
await searchAddressSuggestions(query);

suggestions.forEach((item)=>{

const div=
document.createElement("div");

div.className=
"suggestion-item";

div.textContent=
item.properties.label;

div.addEventListener("click",()=>{

input.value=
item.properties.label;

suggestionsBox.innerHTML="";

});

suggestionsBox.appendChild(div);

});

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
  const url =
    `https://api.openrouteservice.org/geocode/search?api_key=${window.API_KEY}&text=${encodeURIComponent(address)}&boundary.country=FR`;

  const response = await fetch(url);
  const data = await response.json();

  if (!data.features || data.features.length === 0) {
    throw new Error("Adresse introuvable");
  }

  return data.features[0].geometry.coordinates; // [lon, lat]
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

    const routeResponse = await fetch(
      `https://api.openrouteservice.org/v2/directions/driving-car/geojson?api_key=${window.API_KEY}`,
      {
        method: "POST",
        headers: {
          Authorization: window.API_KEY,
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          coordinates: [startCoords, endCoords]
        })
      }
    );

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


reservationForm.addEventListener("submit", () => {
  document.getElementById("hiddenStart").value = startInput.value;
  document.getElementById("hiddenEnd").value = endInput.value;
  document.getElementById("hiddenDistance").value = distanceText.textContent;
  document.getElementById("hiddenDuration").value = durationText.textContent;
  document.getElementById("hiddenPrice").value = priceText.textContent;
});