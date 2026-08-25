
const burger = document.getElementById("burger");
const navbar = document.getElementById("navbar");

if (burger && navbar) {
  burger.addEventListener("click", () => {
    navbar.classList.toggle("active");
  });
}

const nowBtn = document.getElementById("nowBtn");
const laterBtn = document.getElementById("laterBtn");
const dateTimeBox = document.getElementById("dateTimeBox");
const momentHero = document.getElementById("momentHero");

if (nowBtn && laterBtn && dateTimeBox) {
  nowBtn.addEventListener("click", () => {
    nowBtn.classList.add("active");
    laterBtn.classList.remove("active");
    dateTimeBox.classList.remove("show");

    if (momentHero) {
      momentHero.value = "Maintenant";
    }
  });

  laterBtn.addEventListener("click", () => {
    laterBtn.classList.add("active");
    nowBtn.classList.remove("active");
    dateTimeBox.classList.add("show");

    if (momentHero) {
      momentHero.value = "Plus tard";
    }
  });
}

const departInput = document.getElementById("depart");
const arriveeInput = document.getElementById("arrivee");

const departSuggestions = document.getElementById("departSuggestions");
const arriveeSuggestions = document.getElementById("arriveeSuggestions");

async function searchIdfAddressSuggestions(query) {
  if (query.length < 4) return [];

  try {
    const response = await fetch(
      `/api/address-suggestions?q=${encodeURIComponent(query)}`,
      {
        headers: {
          Accept: "application/json",
        },
      }
    );

    if (!response.ok) {
      return [];
    }

    const data = await response.json();

    return data.features || [];
  } catch (error) {
    console.error("Address autocomplete error:", error);

    return [];
  }
}

function setupIdfAutocomplete(input, suggestionsBox) {
  if (!input || !suggestionsBox) return;

  input.addEventListener("input", async () => {
    const query = input.value.trim();
    suggestionsBox.innerHTML = "";

    const suggestions = await searchIdfAddressSuggestions(query);

    suggestions.forEach((item) => {
      const div = document.createElement("div");
      div.className = "suggestion-item";
      div.textContent = item.properties.label;

      div.addEventListener("click", () => {
        input.value = item.properties.label;
        suggestionsBox.innerHTML = "";
      });

      suggestionsBox.appendChild(div);
    });
  });
}

setupIdfAutocomplete(departInput, departSuggestions);
setupIdfAutocomplete(arriveeInput, arriveeSuggestions);

const fleetData = {
  eco: {
    image: "assets/images/eco.PNG",
    rate: "Tarif indicatif : 1,70€/km",
    title: "Gamme Éco",
    description: "Une solution idéale pour vos trajets du quotidien à Paris, avec confort, fiabilité et prix maîtrisé.",
    passengers: "4",
    bags: "2",
  },
  berline: {
    image: "assets/images/berline.PNG",
    rate: "Tarif indicatif : 2,00€/km",
    title: "Gamme Berline",
    description: "Un véhicule élégant et confortable pour vos transferts, rendez-vous professionnels et trajets premium.",
    passengers: "4",
    bags: "3",
  },
  van: {
    image: "assets/images/van.PNG",
    rate: "Tarif indicatif : 2,50€/km",
    title: "Gamme Van",
    description: "La solution idéale pour les familles, groupes et trajets avec plusieurs bagages.",
    passengers: "7",
    bags: "6",
  },
};

const fleetTabs = document.querySelectorAll(".fleet-tab");
const fleetImage = document.getElementById("fleetImage");
const fleetRate = document.getElementById("fleetRate");
const fleetTitle = document.getElementById("fleetTitle");
const fleetDescription = document.getElementById("fleetDescription");
const fleetPassengers = document.getElementById("fleetPassengers");
const fleetBags = document.getElementById("fleetBags");

fleetTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    const car = tab.dataset.car;
    const data = fleetData[car];

    fleetTabs.forEach((item) => item.classList.remove("active"));
    tab.classList.add("active");

    fleetImage.src = data.image;
    fleetRate.textContent = data.rate;
    fleetTitle.textContent = data.title;
    fleetDescription.textContent = data.description;
    fleetPassengers.textContent = data.passengers;
    fleetBags.textContent = data.bags;
  });
});
