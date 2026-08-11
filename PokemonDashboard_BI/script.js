let currentRole = "admin";
let factRaw = [], dimRaw = [], fullData = [], securedData = [], filteredData = [];

const chartLayout = {
  paper_bgcolor: "rgba(0,0,0,0)",
  plot_bgcolor: "rgba(0,0,0,0)",
  font: { color: "#e5e7eb" },
  margin: { t: 25, r: 15, b: 45, l: 45 },
  xaxis: { gridcolor: "rgba(255,255,255,.08)" },
  yaxis: { gridcolor: "rgba(255,255,255,.08)" }
};
const chartConfig = { responsive: true, displayModeBar: false };

// --- Control de Inicio de Sesión y Roles ---
document.getElementById("enterBtn").addEventListener("click", () => {
  currentRole = document.getElementById("roleSelect").value;
  document.getElementById("loginScreen").classList.add("d-none");
  document.getElementById("appShell").classList.remove("d-none");
  updateRoleLabel();
  applySecurity();
});

document.getElementById("changeRoleBtn").addEventListener("click", () => location.reload());
document.getElementById("autoBtn").addEventListener("click", autoLoad);
document.getElementById("factFile").addEventListener("change", e => readCSV(e.target.files[0], "fact"));
document.getElementById("dimFile").addEventListener("change", e => readCSV(e.target.files[0], "dim"));
document.getElementById("resetBtn").addEventListener("click", resetFilters);

// --- Inicialización de Listeners de Filtros OLAP ---
["trainerFilter", "zoneFilter", "capturedFilter", "pokemonSearch", "yearFilter", "monthFilter", "minLevel", "maxLevel", "tableSearch"]
  .forEach(id => document.getElementById(id).addEventListener("input", processDataPipeline));

function updateRoleLabel() {
  const labels = {
    admin: "Administrador - Acceso Completo",
    basico: "Analista Junior - Restringido (Ruta 1 / Bosque Verde)",
    zona: "Supervisor Regional - Restringido (Zona Safari)"
  };
  document.getElementById("roleLabel").textContent = labels[currentRole] || currentRole;
}

// --- Lector de Archivos CSV ---
function readCSV(file, type) {
  if (!file) return;
  Papa.parse(file, {
    header: true,
    dynamicTyping: true,
    skipEmptyLines: true,
    complete: function (results) {
      if (type === "fact") factRaw = results.data;
      else dimRaw = results.data;

      checkAndJoin();
    }
  });
}

// --- Simulación de Carga Automática ---
function autoLoad() {
  Promise.all([
    fetch('data/fact_encounters.csv').then(r => r.text()),
    fetch('data/dim_pokemon.csv').then(r => r.text())
  ]).then(([factTxt, dimTxt]) => {
    factRaw = Papa.parse(factTxt, { header: true, dynamicTyping: true, skipEmptyLines: true }).data;
    dimRaw = Papa.parse(dimTxt, { header: true, dynamicTyping: true, skipEmptyLines: true }).data;
    checkAndJoin();
  }).catch(err => {
    alert("No se pudieron cargar automáticamente los archivos de la carpeta data/. Por favor, selecciónalos manualmente con los botones superiores.");
  });
}

// --- Join Analítico (Paso 3 del Data Warehouse) ---
function checkAndJoin() {
  if (!factRaw.length || !dimRaw.length) return;

  // Creamos un mapa de la dimensión para búsquedas O(1) eficientes
  const dimMap = new Map();
  dimRaw.forEach(p => {
    if (p.pokemon_id) dimMap.set(p.pokemon_id, p);
  });

  // Hacemos el Join formal basado en la llave foránea pokemon_id
  fullData = factRaw.map(f => {
    const p = dimMap.get(f.pokemon_id) || { name: "Desconocido", base_experience: 50 };

    // Estandarización interna de fechas con el motor de JS
    let parsedDate = new Date();
    if (f.date) {
      parsedDate = new Date(f.date.replace(/-/g, "/"));
    }

    return {
      ...f,
      name: p.name,
      base_experience: p.base_experience,
      date: parsedDate,
      captured: String(f.captured).toLowerCase() === "true"
    };
  });

  applySecurity();
}

// --- CAPA DE SEGURIDAD RLS (Paso 5) ---
function applySecurity() {
  if (!fullData.length) return;

  // Filtro Estricto de Filas según el rol del CSV mapeado por texto exacto
  if (currentRole === "admin") {
    securedData = [...fullData];
  } else if (currentRole === "basico") {
    securedData = fullData.filter(e => e.zone === "Ruta 1" || e.zone === "Bosque Verde");
  } else if (currentRole === "zona") {
    securedData = fullData.filter(e => e.zone === "Zona Safari");
  }

  populateFilterDropdowns();
  resetFilters();
}

function populateFilterDropdowns() {
  const trainers = [...new Set(securedData.map(e => e.trainer))].sort();
  const zones = [...new Set(securedData.map(e => e.zone))].sort();

  updateDropdown("trainerFilter", trainers);
  updateDropdown("zoneFilter", zones);
}

function updateDropdown(id, items) {
  const select = document.getElementById(id);
  select.innerHTML = '<option value="">Todos</option>';
  items.forEach(item => {
    if (item) select.innerHTML += `<option value="${item}">${item}</option>`;
  });
}

function resetFilters() {
  document.getElementById("trainerFilter").value = "";
  document.getElementById("zoneFilter").value = "";
  document.getElementById("capturedFilter").value = "";
  document.getElementById("pokemonSearch").value = "";
  document.getElementById("yearFilter").value = "";
  document.getElementById("monthFilter").value = "";
  document.getElementById("minLevel").value = 1;
  document.getElementById("maxLevel").value = 100;
  document.getElementById("tableSearch").value = "";

  processDataPipeline();
}

// --- PIPELINE DE FILTRADO OLAP Y RENDERIZADO ---
function processDataPipeline() {
  if (!securedData.length) return;

  const tFilter = document.getElementById("trainerFilter").value;
  const zFilter = document.getElementById("zoneFilter").value;
  const cFilter = document.getElementById("capturedFilter").value;
  const pSearch = document.getElementById("pokemonSearch").value.toLowerCase();
  const yFilter = document.getElementById("yearFilter").value;
  const mFilter = document.getElementById("monthFilter").value;
  const minL = parseInt(document.getElementById("minLevel").value) || 1;
  const maxL = parseInt(document.getElementById("maxLevel").value) || 100;

  filteredData = securedData.filter(e => {
    if (tFilter && e.trainer !== tFilter) return false;
    if (zFilter && e.zone !== zFilter) return false;
    if (cFilter && String(e.captured) !== cFilter) return false;
    if (pSearch && !e.name.toLowerCase().includes(pSearch)) return false;
    if (yFilter && e.date.getFullYear().toString() !== yFilter) return false;
    if (mFilter && (e.date.getMonth() + 1).toString() !== mFilter) return false;
    if (e.pokemon_level < minL || e.pokemon_level > maxL) return false;
    return true;
  });

  renderKPIs();
  renderCharts();
  renderOLAPMatrix();
  renderTable();
}

// --- CAPA ANALÍTICA: MEDIDAS CALCULADAS TIPO DAX ---
function renderKPIs() {
  const total = filteredData.length;
  const capturedCount = filteredData.filter(e => e.captured).length;
  const captureRate = total ? ((capturedCount / total) * 100) : 0;
  const avgCP = total ? avg(filteredData.map(e => e.combat_power)) : 0;

  document.getElementById("kpiEncounters").textContent = total.toLocaleString();
  document.getElementById("kpiCaptured").textContent = capturedCount.toLocaleString();
  document.getElementById("kpiCaptureRate").textContent = `${captureRate.toFixed(1)}%`;
  document.getElementById("kpiAvgCP").textContent = avgCP.toFixed(0);

  // Métrica MoM (Crecimiento porcentual mes a mes)
  document.getElementById("kpiGrowth").innerHTML = calculateMonthlyGrowth();
}

function avg(arr) {
  return arr.reduce((a, b) => a + b, 0) / arr.length;
}

function calculateMonthlyGrowth() {
  const monthlyCounts = {};
  filteredData.forEach(e => {
    const key = `${e.date.getFullYear()}-${String(e.date.getMonth() + 1).padStart(2, '0')}`;
    monthlyCounts[key] = (monthlyCounts[key] || 0) + 1;
  });

  const sortedMonths = Object.keys(monthlyCounts).sort();
  if (sortedMonths.length < 2) return `<span class="text-muted"><i class="bi bi-dash"></i> N/A (MoM)</span>`;

  const currentMonthKey = sortedMonths[sortedMonths.length - 1];
  const prevMonthKey = sortedMonths[sortedMonths.length - 2];

  const currentCount = monthlyCounts[currentMonthKey];
  const prevCount = monthlyCounts[prevMonthKey];

  const growth = ((currentCount - prevCount) / prevCount) * 100;

  if (growth >= 0) {
    return `<span class="text-success"><i class="bi bi-arrow-up-right"></i> +${growth.toFixed(1)}% MoM</span>`;
  } else {
    return `<span class="text-danger"><i class="bi bi-arrow-down-left"></i> ${growth.toFixed(1)}% MoM</span>`;
  }
}

// --- RENDERIZADO DE GRÁFICOS (PLOTLY.JS) ---
function renderCharts() {
  // 1. Histograma de Línea Temporal
  const dates = filteredData.map(e => e.date.toISOString().split('T')[0]);
  const dateCounts = countBy(dates);
  const sortedDates = Object.keys(dateCounts).sort();

  Plotly.newPlot("timelineChart", [{
    x: sortedDates,
    y: sortedDates.map(d => dateCounts[d]),
    type: 'scatter', mode: 'lines',
    line: { color: '#ffcb05', width: 3 },
    fill: 'tozeroy', fillcolor: 'rgba(255,203,5,.05)'
  }], chartLayout, chartConfig);

  // 2. Gráfico de Barras: Capturas por Zona
  const zoneCounts = countBy(filteredData, "zone");
  const zoneData = Object.entries(zoneCounts).sort((a, b) => b[1] - a[1]);

  Plotly.newPlot("zoneChart", [{
    x: zoneData.map(z => z[0]),
    y: zoneData.map(z => z[1]),
    type: 'bar',
    marker: { color: '#2a75bb', line: { color: 'rgba(255,255,255,.1)', width: 1 } }
  }], chartLayout, chartConfig);

  // 3. Gráfico de Barras: Top 10 Pokémon más comunes
  const pokeCounts = countBy(filteredData, "name");
  const topPoke = Object.entries(pokeCounts).sort((a, b) => b[1] - a[1]).slice(0, 10);

  Plotly.newPlot("pokemonChart", [{
    x: topPoke.map(p => p[0]),
    y: topPoke.map(p => p[1]),
    type: 'bar',
    marker: { color: '#22c55e' }
  }], chartLayout, chartConfig);

  // Configurar los Triggers para el Drill-Through interactivo
  document.getElementById("zoneChart").on('plotly_click', d => openDetail("zone", d.points[0].x));
  document.getElementById("pokemonChart").on('plotly_click', d => openDetail("name", d.points[0].x));
}

function countBy(arr, prop) {
  const obj = {};
  arr.forEach(x => {
    const val = prop ? x[prop] : x;
    obj[val] = (obj[val] || 0) + 1;
  });
  return obj;
}

// --- MATRIZ CRUZADA OLAP (Entrenador vs Zona) ---
function renderOLAPMatrix() {
  const trainers = [...new Set(filteredData.map(e => e.trainer))].sort();
  const zones = [...new Set(filteredData.map(e => e.zone))].sort();

  const crossTab = {};
  trainers.forEach(t => { crossTab[t] = {}; zones.forEach(z => crossTab[t][z] = 0); });
  filteredData.forEach(e => { if (crossTab[e.trainer] && crossTab[e.trainer][e.zone] !== undefined) crossTab[e.trainer][e.zone]++; });

  let html = `<thead><tr><th>Entrenador</th>${zones.map(z => `<th>${z}</th>`).join('')}<th>Total</th></tr></thead><tbody>`;

  trainers.forEach(t => {
    let rowSum = 0;
    html += `<tr><td><strong>${t}</strong></td>`;
    zones.forEach(z => {
      const count = crossTab[t][z];
      rowSum += count;
      html += `<td>${count.toLocaleString()}</td>`;
    });
    html += `<td class="text-warning font-weight-bold">${rowSum.toLocaleString()}</td></tr>`;
  });

  html += `</tbody>`;
  document.getElementById("olapMatrix").innerHTML = html;
}

// --- RENDERIZADO DE TABLA TRANSACCIONAL (Granularidad Baja) ---
function renderTable() {
  const sText = document.getElementById("tableSearch").value.toLowerCase();
  let data = filteredData;

  if (sText) {
    data = filteredData.filter(e =>
      e.name.toLowerCase().includes(sText) ||
      e.trainer.toLowerCase().includes(sText) ||
      e.zone.toLowerCase().includes(sText)
    );
  }

  const limit = 150;
  const chunk = data.slice(0, limit);
  document.getElementById("dataTable").innerHTML = chunk.map(rowHTML).join('');
  document.getElementById("tableNote").textContent = `Mostrando máximo ${limit} de ${data.length.toLocaleString()} registros filtrados.`;
}

function rowHTML(e) {
  return `<tr>
    <td>${e.encounter_id}</td>
    <td><strong>${e.name}</strong></td>
    <td>${e.trainer}</td>
    <td>${e.zone}</td>
    <td>${e.pokemon_level}</td>
    <td>${e.combat_power}</td>
    <td><span class="badge ${e.captured ? "badge-captured" : "badge-failed"}">${e.captured ? "Sí" : "No"}</span></td>
    <td>${e.date.toLocaleString("es-MX")}</td>
  </tr>`;
}

// --- INTERFAZ INTERACTIVA: DRILL-THROUGH (Paso 5) ---
function openDetail(field, value) {
  const data = filteredData.filter(e => e[field] === value);
  document.getElementById("detailTitle").textContent = `Drill-through: Detalle por ${field === "zone" ? "Zona" : "Pokémon"} -> ${value}`;

  const total = data.length;
  const cap = data.filter(x => x.captured).length;

  document.getElementById("detailKpis").innerHTML = `
    <div><span>Encuentros Históricos</span><strong>${total.toLocaleString()}</strong></div>
    <div><span>Capturados</span><strong>${cap.toLocaleString()}</strong></div>
    <div><span>Ratio de Éxito</span><strong>${total ? ((cap / total) * 100).toFixed(1) : 0}%</strong></div>
    <div><span>Fuerza Promedio (CP)</span><strong>${total ? avg(data.map(x => x.combat_power)).toFixed(0) : 0}</strong></div>
  `;

  const c = countBy(data, field === "zone" ? "name" : "zone");
  const top = Object.entries(c).sort((a, b) => b[1] - a[1]).slice(0, 10);

  Plotly.newPlot("detailChart", [{
    x: top.map(x => x[0]),
    y: top.map(x => x[1]),
    type: 'bar',
    marker: { color: '#ffcb05' }
  }], { ...chartLayout, margin: { t: 15, r: 15, b: 60, l: 45 } }, chartConfig);

  document.getElementById("detailTable").innerHTML = data.slice(0, 50).map(rowHTML).join('');

  const myModal = new bootstrap.Modal(document.getElementById('detailModal'));
  myModal.show();
}