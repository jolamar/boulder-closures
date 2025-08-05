<script setup>
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'
import { onMounted, ref, watch } from 'vue'

const map = ref(null)
const closures = ref([])
const filteredClosures = ref([])
const favorites = ref(new Set()) // Store favorite IDs
const searchTerm = ref('')
const showOnlyFavorites = ref(false)
const markers = ref([])

async function loadFavorites() {
    const res = await fetch('/favorites') // or your real endpoint
    favorites.value = await res.json()
}


function webMercatorToLatLng(x, y) {
    const lng = (x / 20037508.34) * 180
    const lat = (y / 20037508.34) * 180
    const latRadians = (lat * Math.PI) / 180
    const latFinal = (180 / Math.PI) * (2 * Math.atan(Math.exp(latRadians)) - Math.PI / 2)
    return { lat: latFinal, lng }
}

function isFavorited(feature) {
    return favorites.value.includes(feature)
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content')
}

async function toggleFavorite(feature) {
    const already = isFavorited(feature)

    if (already) {
        await fetch('/favorites', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ feature }),
        })
        favorites.value = favorites.value.filter(f => f !== feature)
    } else {
        await fetch('/favorites', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ feature }),
        })
        favorites.value.push(feature.name)
    }
}

const redIcon = new L.Icon({
    iconUrl: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -32],
})

const greenIcon = new L.Icon({
    iconUrl: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -32],
})

function updateMarkers() {
    // Remove existing markers
    markers.value.forEach(m => map.value.removeLayer(m))
    markers.value = []

    // Add new filtered markers
    filteredClosures.value.forEach(point => {
        const icon = point.status === 'closed' ? redIcon : greenIcon
        const marker = L.marker([point.lat, point.lng], { icon })
            .addTo(map.value)
            .bindPopup(() => {
                const container = document.createElement('div')
                container.innerHTML = `
                <strong>${point.name}</strong><br>
                Status: ${point.status}<br>
                <button class="favorite-btn">${favorites.value.includes(point.name) ? '★ Unfavorite' : '☆ Favorite'}</button>
            `

                        container.querySelector('.favorite-btn').addEventListener('click', () => {
                            toggleFavorite(point)
                            marker.closePopup()
                            marker.openPopup() // Rerenders popup with updated star
                        })

                        return container
                    })

        markers.value.push(marker)
    })
}

// Filter closures when searchTerm changes
watch(searchTerm, (term) => {
    const lower = term.toLowerCase()
    filteredClosures.value = closures.value.filter(c => c.name.toLowerCase().includes(lower))
    updateMarkers()
})

watch([searchTerm, showOnlyFavorites, favorites], (term) => {
    applyFilters()
})

function applyFilters() {
    const lower = searchTerm.value.toLowerCase()

    filteredClosures.value = closures.value.filter(c => {
        const matchesSearch = c.name.toLowerCase().includes(lower)
        const isFavorite = favorites.value.includes(c.name) // or c.id, depending on what you store
        return matchesSearch && (!showOnlyFavorites.value || isFavorite)
    })

    updateMarkers()
}

onMounted(async () => {
    map.value = L.map('map').setView([39.998, -105.283], 13)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map.value)

    const res = await fetch('https://gis.bouldercolorado.gov/ags_svr2/rest/services/osmp/WildlifeClosuresCurrentClimbingAccessHCA/MapServer/1/query?f=json&cacheHint=true&maxRecordCountFactor=4&resultOffset=0&resultRecordCount=2000&where=1%3D1&orderByFields=OBJECTID%20ASC&outFields=ClosureActive%2CFEATURE%2COBJECTID%2COWNER%2CSeasonalClosure&outSR=102100&spatialRel=esriSpatialRelIntersects')
    const json = await res.json()

    closures.value = json.features.map(f => {
        const { x, y } = f.geometry
        const { lat, lng } = webMercatorToLatLng(x, y)

        return {
            id: f.attributes.OBJECTID,
            name: f.attributes.FEATURE,
            status: f.attributes.ClosureActive === 'Y' ? 'closed' : 'open',
            lat,
            lng,
        }
    })

    await loadFavorites()

    filteredClosures.value = closures.value // Initial state: show all
    updateMarkers()
})
</script>

<template>
    <div class="h-screen w-screen relative">
        <input
            v-model="searchTerm"
            type="text"
            placeholder="Search flatirons..."
            class="absolute top-4 left-12 z-[1000] p-2 rounded bg-white border shadow"
        />

        <label class="absolute top-4 right-12 z-[1000] bg-white p-2 rounded shadow cursor-pointer select-none">
            <input type="checkbox" v-model="showOnlyFavorites" class="mr-2" />
            Show Favorites
        </label>
        <div id="map"></div>
    </div>
</template>

<style scoped>
#map {
    height: 100vh;
    width: 100vw;
}

.favorite-btn {
    background: none;
    border: none;
    color: #f39c12;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 5px;
}
</style>
