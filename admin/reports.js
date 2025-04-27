// Globális változók a grafikonok tárolásához
let currentChart = null;
const chartColors = {
    primary: [
        'rgba(75, 192, 192, 0.8)',
        'rgba(54, 162, 235, 0.8)',
        'rgba(153, 102, 255, 0.8)',
        'rgba(255, 159, 64, 0.8)',
        'rgba(255, 99, 132, 0.8)',
        'rgba(255, 206, 86, 0.8)'
    ],
    borders: [
        'rgba(75, 192, 192, 1)',
        'rgba(54, 162, 235, 1)',
        'rgba(153, 102, 255, 1)',
        'rgba(255, 159, 64, 1)',
        'rgba(255, 99, 132, 1)',
        'rgba(255, 206, 86, 1)'
    ]
};

// Statisztika típusok és azok beállításai
const chartTypes = {
    packageDistribution: {
        title: 'Előfizetési csomagok megoszlása',
        type: 'doughnut',
        endpoint: 'getStats',
        description: 'Az aktív előfizetések megoszlása csomagok szerint',
        showTimeRange: true
    },
    subscriptionType: {
        title: 'Előfizetési típus preferencia',
        type: 'bar',
        endpoint: 'getSubscriptionTypeStats',
        description: 'Havi vs. éves előfizetések aránya',
        showTimeRange: true
    },
    timeDistribution: {
        title: 'Előfizetések időbeli eloszlása',
        type: 'line',
        endpoint: 'getTimeDistribution',
        description: 'Új előfizetések száma időszakonként',
        showTimeRange: true
    },
    financial: {
        title: 'Pénzügyi áttekintés',
        type: 'bar',
        endpoint: 'getFinancialStats',
        description: 'Bevételek alakulása időszakonként',
        showTimeRange: true
    },
    modificationTypes: {
        title: 'Módosítások típusai',
        type: 'pie',
        endpoint: 'getModificationTypes',
        description: 'Előfizetés módosítások típusainak megoszlása',
        showTimeRange: false
    }
};

async function showSubscriptionStats() {
    try {
        const popup = createPopup({
            theme: 'subscription-stats',
            title: 'Előfizetési statisztikák',
            width: 1200,
            height: 800,
            top: 50,
            content: `
                <div class="stats-container">
                    <div class="stats-header">
                        <select id="chartTypeSelect" class="chart-type-select">
                            ${Object.entries(chartTypes).map(([key, value]) => 
                                `<option value="${key}">${value.title}</option>`
                            ).join('')}
                        </select>
                        <select id="timeRangeSelect" class="time-range-select">
                            <option value="last30days">Utolsó 30 nap</option>
                            <option value="last3months">Utolsó 3 hónap</option>
                            <option value="last6months">Utolsó 6 hónap</option>
                            <option value="lastyear">Utolsó 1 év</option>
                            <option value="all">Összes</option>
                        </select>
                        <button id="exportBtn" class="export-btn">
                            <i class="fas fa-download"></i> Exportálás
                        </button>
                    </div>
                    <p class="chart-description"></p>
                    <div class="chart-container" style="width: 90%; height: 150px; margin: 0 auto 30px auto; color: var(--text-color); background-color: var(--card-bg); padding: 20px; border-radius: 12px; position: relative;">
                        <canvas id="statsChart"></canvas>
                    </div>
                    <div class="stats-details">
                        <div class="stats-summary"></div>
                    </div>
                </div>
            `
        });

        // Események kezelése
        const chartTypeSelect = popup.querySelector('#chartTypeSelect');
        const timeRangeSelect = popup.querySelector('#timeRangeSelect');
        const exportBtn = popup.querySelector('#exportBtn');
        const chartDescription = popup.querySelector('.chart-description');

        // Leírás frissítése és időszak választó megjelenítése/elrejtése
        const updateUI = () => {
            const selectedType = chartTypeSelect.value;
            chartDescription.textContent = chartTypes[selectedType].description;
            timeRangeSelect.style.display = chartTypes[selectedType].showTimeRange ? 'block' : 'none';
        };

        chartTypeSelect.addEventListener('change', () => {
            loadChartData(chartTypeSelect.value);
            updateUI();
        });
        timeRangeSelect.addEventListener('change', () => loadChartData(chartTypeSelect.value));
        exportBtn.addEventListener('click', exportData);

        // Kezdeti UI beállítása
        updateUI();
        await loadChartData('packageDistribution');

    } catch (error) {
        console.error('Error showing stats:', error);
        showError('Hiba', 'Nem sikerült betölteni a statisztikákat.');
    }
}

async function loadChartData(chartType) {
    try {
        const timeRange = document.querySelector('#timeRangeSelect').value;
        const response = await fetch(`subscription_details.php?action=${chartTypes[chartType].endpoint}&timeRange=${timeRange}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message);
        }

        // Töröljük a régi diagramot, ha létezik
        if (currentChart) {
            currentChart.destroy();
        }

        const ctx = document.getElementById('statsChart').getContext('2d');
        let chartConfig = {
            type: chartTypes[chartType].type,
            data: result.data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-color')
                        }
                    }
                }
            }
        };

        // Speciális beállítások a doughnut diagramhoz
        if (chartType === 'packageDistribution') {
            // Ellenőrizzük az adatszerkezetet és számoljuk ki az összeget
            let totalSubscriptions = 0;
            let detailsData = [];

            if (Array.isArray(result.data)) {
                // Ha az adat egy egyszerű tömb
                totalSubscriptions = result.data.reduce((sum, item) => sum + item.count, 0);
                detailsData = result.data;
            } else if (result.data.datasets && result.data.datasets[0]) {
                // Ha az adat már Chart.js formátumban van
                totalSubscriptions = result.data.datasets[0].data.reduce((a, b) => a + b, 0);
                detailsData = result.data.labels.map((label, index) => ({
                    package_name: label,
                    count: result.data.datasets[0].data[index]
                }));
            }

            // Diagram középső szöveg beállítása
            chartConfig.options.plugins.doughnutLabel = {
                id: 'doughnutLabel',
                beforeDraw(chart) {
                    const {ctx, width, height} = chart;
                    ctx.save();
                    const fontSize = (height / 160).toFixed(2);
                    ctx.font = `${fontSize}em sans-serif`;
                    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-color');
                    ctx.textBaseline = 'middle';
                    const text = `Összes:\n${totalSubscriptions}`;
                    const textArray = text.split('\n');
                    const lineHeight = fontSize * 1.5;
                    
                    let textY = height / 2 - (textArray.length - 1) * lineHeight / 2;
                    textArray.forEach(line => {
                        const textX = Math.round((width - ctx.measureText(line).width) / 2);
                        ctx.fillText(line, textX, textY);
                        textY += lineHeight * 16;
                    });
                    ctx.restore();
                }
            };
            chartConfig.options.cutout = '60%';

            // Ha az adat még nincs Chart.js formátumban, átalakítjuk
            if (Array.isArray(result.data)) {
                chartConfig.data = {
                    labels: result.data.map(item => item.package_name),
                    datasets: [{
                        data: result.data.map(item => item.count),
                        backgroundColor: chartColors.primary.slice(0, result.data.length),
                        borderColor: chartColors.borders.slice(0, result.data.length),
                        borderWidth: 1
                    }]
                };
            }

            // Részletes statisztikák megjelenítése
            const summaryContainer = document.querySelector('.stats-summary');
            if (summaryContainer) {
                // Megkeressük a legnagyobb előfizetőszámmal rendelkező csomagot
                const maxSubscriptions = Math.max(...detailsData.map(item => item.count));
                
                let summaryHTML = `<div class="total-subscriptions">Összes előfizető: ${totalSubscriptions}</div><div class="package-details">`;
                detailsData.forEach((item, index) => {
                    const percentage = ((item.count / totalSubscriptions) * 100).toFixed(1);
                    const isPopular = item.count === maxSubscriptions;
                    summaryHTML += `
                        <div class="package-stat" 
                             style="border-left: 4px solid ${chartColors.primary[index]}">
                            <div class="package-header">
                                <div class="package-name">${item.package_name}</div>
                                ${isPopular ? '<div class="trend-indicator"><i class="fas fa-chart-line"></i></div>' : ''}
                            </div>
                            <div class="package-metrics">
                                <div class="metric">
                                    <span class="metric-value">${item.count}</span>
                                    <span class="metric-label">előfizető</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-value">${percentage}%</span>
                                    <span class="metric-label">részesedés</span>
                                </div>
                            </div>
                            ${isPopular ? '<div class="popularity-indicator"></div>' : ''}
                        </div>
                    `;
                });
                summaryHTML += '</div>';
                summaryContainer.innerHTML = summaryHTML;
            }
        } else if (chartType === 'financial') {
            // Pénzügyi adatok feldolgozása
            if (Array.isArray(result.data)) {
                const totalMonthlyRevenue = result.data.reduce((sum, item) => sum + parseFloat(item.monthly_revenue), 0);
                const totalYearlyRevenue = result.data.reduce((sum, item) => sum + parseFloat(item.yearly_revenue), 0);
                const totalMonthlySubscriptions = result.data.reduce((sum, item) => sum + parseInt(item.monthly_subs), 0);
                const totalYearlySubscriptions = result.data.reduce((sum, item) => sum + parseInt(item.yearly_subs), 0);

                // Részletes statisztikák megjelenítése
                const summaryContainer = document.querySelector('.stats-summary');
                if (summaryContainer) {
                    let summaryHTML = `
                        <div class="financial-summary">
                            <div class="revenue-card monthly">
                                <h3>Havi előfizetések</h3>
                                <div class="revenue-details">
                                    <div class="metric">
                                        <span class="metric-value">${new Intl.NumberFormat('hu-HU').format(totalMonthlyRevenue)} Ft</span>
                                        <span class="metric-label">összes bevétel</span>
                                    </div>
                                    <div class="metric">
                                        <span class="metric-value">${totalMonthlySubscriptions}</span>
                                        <span class="metric-label">előfizetés</span>
                                    </div>
                                </div>
                            </div>
                            <div class="revenue-card yearly">
                                <h3>Éves előfizetések</h3>
                                <div class="revenue-details">
                                    <div class="metric">
                                        <span class="metric-value">${new Intl.NumberFormat('hu-HU').format(totalYearlyRevenue)} Ft</span>
                                        <span class="metric-label">összes bevétel</span>
                                    </div>
                                    <div class="metric">
                                        <span class="metric-value">${totalYearlySubscriptions}</span>
                                        <span class="metric-label">előfizetés</span>
                                    </div>
                                </div>
                            </div>
                            <div class="revenue-card total">
                                <h3>Összes bevétel</h3>
                                <div class="revenue-details">
                                    <div class="metric">
                                        <span class="metric-value">${new Intl.NumberFormat('hu-HU').format(totalMonthlyRevenue + totalYearlyRevenue)} Ft</span>
                                        <span class="metric-label">teljes bevétel</span>
                                    </div>
                                    <div class="metric">
                                        <span class="metric-value">${totalMonthlySubscriptions + totalYearlySubscriptions}</span>
                                        <span class="metric-label">összes előfizetés</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    summaryContainer.innerHTML = summaryHTML;
                }
            }
        } else if (chartType === 'modificationTypes') {
            // Módosítások típusainak feldolgozása
            const data = result.data;
            
            if (!data || !data.package_modifications) {
                throw new Error('Hiányzó vagy érvénytelen adatok a módosítások megjelenítéséhez');
            }

            // Részletes statisztikák megjelenítése
            const statsDetails = document.querySelector('.stats-details');
            
            // Módosítás típusok kártyái
            let modificationTypesHTML = `
                <div class="modification-types">
                    <div class="modification-card no-changes">
                        <i class="fas fa-check-circle"></i>
                        <h3>Nem módosított</h3>
                        <div class="count">${data.no_modifications || 0}</div>
                        <div class="percentage">${data.total > 0 ? ((data.no_modifications / data.total) * 100).toFixed(1) : 0}%</div>
                    </div>
                    <div class="modification-card user-only">
                        <i class="fas fa-users"></i>
                        <h3>Csak felhasználószám</h3>
                        <div class="count">${data.user_limit_only || 0}</div>
                        <div class="percentage">${data.total > 0 ? ((data.user_limit_only / data.total) * 100).toFixed(1) : 0}%</div>
                    </div>
                    <div class="modification-card device-only">
                        <i class="fas fa-laptop"></i>
                        <h3>Csak eszközszám</h3>
                        <div class="count">${data.device_limit_only || 0}</div>
                        <div class="percentage">${data.total > 0 ? ((data.device_limit_only / data.total) * 100).toFixed(1) : 0}%</div>
                    </div>
                    <div class="modification-card both-modified">
                        <i class="fas fa-sync-alt"></i>
                        <h3>Mindkettő módosítva</h3>
                        <div class="count">${data.both_modified || 0}</div>
                        <div class="percentage">${data.total > 0 ? ((data.both_modified / data.total) * 100).toFixed(1) : 0}%</div>
                    </div>
                </div>`;

            // Csomagok módosításainak megjelenítése
            let packageModificationsHTML = `
                <div class="package-modifications">
                    <h2>Módosítások csomagok szerint</h2>
                    <div class="package-modifications-grid">
                        ${data.package_modifications.map((pkg, index) => `
                            <div class="package-mod-card ${index === 0 ? 'most-modified' : ''}">
                                <h3>${formatPackageName(pkg.package_name)}</h3>
                                <div class="mod-count">
                                    <i class="fas fa-edit"></i>
                                    <span>${pkg.count} módosítás</span>
                                </div>
                                <div class="mod-percentage">
                                    ${((pkg.count / data.total_package_modifications) * 100).toFixed(1)}% az összes módosításból
                                </div>
                                ${index === 0 ? '<div class="most-modified-badge"><i class="fas fa-crown"></i> Legtöbb módosítás</div>' : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>`;

            statsDetails.innerHTML = modificationTypesHTML + packageModificationsHTML;

            // Kördiagram adatok előkészítése
            chartConfig = {
                type: 'pie',
                data: {
                    labels: ['Nem módosított', 'Csak felhasználószám', 'Csak eszközszám', 'Mindkettő módosítva'],
                    datasets: [{
                        data: [
                            data.no_modifications || 0,
                            data.user_limit_only || 0,
                            data.device_limit_only || 0,
                            data.both_modified || 0
                        ],
                        backgroundColor: [
                            '#4CAF50',  // zöld a nem módosítottaknak
                            '#2196F3',  // kék a felhasználószám módosításoknak
                            '#FF9800',  // narancssárga az eszközszám módosításoknak
                            '#9C27B0'   // lila a mindkettő módosításnak
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-color')
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const total = data.total;
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${value} módosítás (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            };
        }

        // Létrehozzuk az új diagramot
        currentChart = new Chart(ctx, chartConfig);

    } catch (error) {
        console.error('Error loading chart data:', error);
        showError('Hiba', 'Nem sikerült betölteni az adatokat.');
    }
}

async function exportData() {
    try {
        console.log("Exportálás indítása...");
        const chartType = document.querySelector('#chartTypeSelect').value;
        const timeRange = document.querySelector('#timeRangeSelect').value;
        
        console.log(`Chart type: ${chartType}, Time range: ${timeRange}`);
        console.log(`Endpoint: subscription_details.php?action=${chartTypes[chartType].endpoint}&timeRange=${timeRange}&format=csv`);
        
        const response = await fetch(`subscription_details.php?action=${chartTypes[chartType].endpoint}&timeRange=${timeRange}&format=csv`);
        
        console.log("Válasz státusz:", response.status);
        console.log("Válasz fejlécek:", [...response.headers.entries()]);
        
        if (!response.ok) {
            throw new Error(`HTTP hiba: ${response.status}`);
        }
        
        const blob = await response.blob();
        console.log("Blob típus:", blob.type, "méret:", blob.size);
        
        if (blob.size === 0) {
            throw new Error("Üres válasz érkezett a szervertől");
        }
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `statistics_${chartType}_${timeRange}.csv`;
        document.body.appendChild(a);
        console.log("Letöltési link létrehozva:", a.download);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
        console.log("Exportálás befejezve");
    } catch (error) {
        console.error('Exportálási hiba:', error);
        showError('Hiba', 'Nem sikerült exportálni az adatokat: ' + error.message);
    }
}

// Eseménykezelő hozzáadása a statisztikák ikonhoz
document.addEventListener('DOMContentLoaded', () => {
    const reportsIcon = document.querySelector('[data-theme="reports"]');
    if (reportsIcon) {
        reportsIcon.addEventListener('click', () => {
            createStatsPopup();
            loadModificationTypeStats();
        });
    }
});

// Segédfüggvény a csomag nevek formázásához
function formatPackageName(name) {
    const nameMap = {
        'alap': 'Alap csomag',
        'kozepes': 'Közepes csomag',
        'uzleti': 'Üzleti csomag',
        'alap_eves': 'Alap csomag (éves)',
        'kozepes_eves': 'Közepes csomag (éves)',
        'uzleti_eves': 'Üzleti csomag (éves)'
    };
    return nameMap[name] || name;
}

// Segédfüggvény a csillagok generálásához
function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star"></i>';
        } else if (i - 0.5 <= rating) {
            stars += '<i class="fas fa-star-half-alt"></i>';
        } else {
            stars += '<i class="far fa-star"></i>';
        }
    }
    return stars;
}

// Válasz űrlap megjelenítése
function showResponseForm(feedbackId) {
    createPopup({
        theme: 'feedback-response',
        title: 'Válasz a visszajelzésre',
        content: `
            <div class="response-form">
                <textarea id="adminResponse" rows="4" placeholder="Írja be a válaszát..."></textarea>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closePopup()">Mégse</button>
                    <button class="btn btn-primary" onclick="submitResponse(${feedbackId})">Válasz küldése</button>
                </div>
            </div>
        `,
        width: 500,
        height: 300
    });
}

// Válasz elküldése
async function submitResponse(feedbackId) {
    const response = document.getElementById('adminResponse').value;
    if (!response.trim()) {
        showError('Hiba', 'Kérjük, írjon be egy választ!');
        return;
    }

    try {
        const result = await fetch('subscription_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'respondToFeedback',
                feedbackId: feedbackId,
                response: response
            })
        });

        const data = await result.json();
        if (data.success) {
            closePopup();
            loadChartData('feedback'); // Frissítjük a visszajelzések listáját
            showSuccess('Sikeres', 'A válasz sikeresen elküldve!');
        } else {
            showError('Hiba', data.message || 'Nem sikerült elküldeni a választ.');
        }
    } catch (error) {
        console.error('Error submitting response:', error);
        showError('Hiba', 'Nem sikerült elküldeni a választ.');
    }
}

function loadModificationTypeStats() {
    fetch('subscription_details.php?action=getModificationTypeStats')
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Hiba történt az adatok betöltése során');
            }

            const data = result.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                throw new Error('Érvénytelen adatformátum');
            }

            const ctx = document.getElementById('statsChart');
            if (!ctx) {
                throw new Error('Nem található a grafikon canvas eleme');
            }
            
            // Töröljük a meglévő grafikont, ha van
            if (window.currentChart) {
                window.currentChart.destroy();
            }

            // Alapértelmezett magasság visszaállítása
            const chartContainer = document.querySelector('.chart-container');
            if (chartContainer) {
                chartContainer.style.height = '150px';
            }

            // Téma szín beállítása
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            const textColor = isDarkMode ? '#ffffff' : '#333333';

            // Létrehozzuk az új kördiagramot
            window.currentChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    ...data,
                    datasets: [{
                        ...data.datasets[0],
                        borderWidth: isDarkMode ? 0 : 2,
                        borderColor: isDarkMode ? data.datasets[0].backgroundColor : Array(data.datasets[0].data.length).fill('#ffffff')
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            left: 10,
                            right: 150,
                            top: 0,
                            bottom: 0
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            align: 'center',
                            labels: {
                                color: textColor,
                                font: {
                                    size: 12
                                },
                                padding: 10,
                                boxWidth: 15,
                                boxHeight: 2
                            }
                        },
                        title: {
                            display: true,
                            text: 'Előfizetés módosítások típusainak megoszlása',
                            color: textColor,
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 15
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 12
                            },
                            bodyFont: {
                                size: 12
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgba(255, 255, 255, 0.2)',
                            borderWidth: 1,
                            padding: 10
                        }
                    }
                }
            });

            // Átalakítjuk az adatokat a megfelelő formátumra
            const labels = ['Nem módosított', 'Csak felhasználószám', 'Csak eszközszám', 'Mindkettő módosítva'];
            updatePackageStatCards(labels, data.datasets[0].data);
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Hiba történt a statisztikák betöltése során', error.message);
        });
}

function updatePackageStatCards(labels, data) {
    const total = data.reduce((a, b) => a + b, 0);
    const cards = document.querySelectorAll('.stat-card');
    
    // Töröljük a meglévő kártyákat
    cards.forEach(card => card.remove());
    
    // Megkeressük a legnagyobb értéket
    const maxValue = Math.max(...data);
    
    // Létrehozzuk az új kártyákat
    const statCards = document.querySelector('.stat-cards');
    statCards.style.display = 'grid';
    statCards.style.gridTemplateColumns = 'repeat(auto-fit, minmax(250px, 1fr))';
    statCards.style.gap = '25px';
    statCards.style.padding = '25px';
    statCards.style.marginTop = '20px';
    statCards.style.borderTop = '1px solid var(--border-color)';
    
    labels.forEach((label, index) => {
        const value = data[index];
        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
        const isPopular = value === maxValue;
        
        const card = document.createElement('div');
        card.className = 'stat-card';
        card.style.padding = '25px';
        card.style.borderRadius = '12px';
        card.style.backgroundColor = 'var(--card-bg)';
        card.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
        card.style.transition = 'transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out';
        card.style.marginBottom = '20px';
        card.style.position = 'relative';
        card.style.overflow = 'visible';
        card.style.minHeight = '160px';

        if (isPopular) {
            card.style.border = '3px solid var(--primary-color)';
            card.style.transform = 'scale(1.02)';
        }

        card.innerHTML = `
            <h3 style="margin: 0 0 20px 0; font-size: 1.2em; color: var(--text-color); padding-right: ${isPopular ? '120px' : '0'};">${label}</h3>
            <div class="stat-value" style="font-size: 2em; font-weight: bold; color: var(--primary-color); margin: 15px 0;">${value}</div>
            <div class="stat-percentage" style="font-size: 1.2em; color: var(--text-color);">${percentage}%</div>
            ${isPopular ? `
                <div style="position: absolute; top: -12px; right: -12px; background: var(--primary-color); color: white; padding: 8px 16px; border-radius: 15px; font-size: 1em; white-space: nowrap; z-index: 1; box-shadow: 0 4px 8px rgba(0,0,0,0.2); text-shadow: 1px 1px 2px rgba(0,0,0,0.3); font-weight: bold;">
                    <i class="fas fa-crown" style="margin-right: 4px;"></i> Legnépszerűbb
                </div>
            ` : ''}
        `;
        
        // Hover effektus
        card.addEventListener('mouseover', () => {
            card.style.transform = isPopular ? 'scale(1.04)' : 'scale(1.02)';
            card.style.boxShadow = '0 6px 12px rgba(0,0,0,0.2)';
        });
        
        card.addEventListener('mouseout', () => {
            card.style.transform = isPopular ? 'scale(1.02)' : 'scale(1)';
            card.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
        });
        
        statCards.appendChild(card);
    });
}

function createStatsPopup() {
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDarkMode ? '#ffffff' : '#333333';

    const popupContent = `
        <div class="stats-container">
            <div class="stats-header">
                <select id="statType" style="color: var(--text-color); background-color: var(--card-bg);">
                    <option value="package_distribution">Előfizetési csomagok megoszlása</option>
                    <option value="time_distribution">Előfizetések időbeli eloszlása</option>
                    <option value="financial">Pénzügyi áttekintés</option>
                    <option value="modification_types">Módosítások típusai</option>
                </select>
                <button id="exportStats" class="export-btn" style="color: var(--text-color);">
                    <i class="fas fa-download"></i> Exportálás
                </button>
            </div>
            
            <div class="stats-content">
                <div class="chart-container" style="width: 90%; height: 150px; margin: 0 auto 30px auto; color: var(--text-color); background-color: var(--card-bg); padding: 20px; border-radius: 12px; position: relative;">
                    <canvas id="statsChart"></canvas>
                </div>
                
                <div class="stat-cards">
                </div>
            </div>
        </div>
    `;

    createPopup({
        theme: 'reports',
        title: 'Előfizetési statisztikák',
        content: popupContent,
        width: 1200,
        height: 900,
        taskbarTitle: 'Előfizetési statisztikák'
    });

    // Inicializáljuk a statisztikákat
    const statType = document.getElementById('statType');
    if (statType) {
        statType.addEventListener('change', function(e) {
            switch(e.target.value) {
                case 'package_distribution':
                    loadPackageDistributionStats();
                    break;
                case 'time_distribution':
                    loadTimeDistributionStats();
                    break;
                case 'financial':
                    loadFinancialStats();
                    break;
                case 'modification_types':
                    loadModificationTypeStats();
                    break;
            }
        });

        // Alapértelmezetten betöltjük a csomag megoszlás statisztikát
        loadPackageDistributionStats();
    }
}

// Event listener a dock ikonhoz
document.addEventListener('DOMContentLoaded', function() {
    const reportsIcon = document.querySelector('.dock-icon[data-theme="reports"]');
    if (reportsIcon) {
        reportsIcon.addEventListener('click', createStatsPopup);
    }
    
    // Globális exportálás gomb eseménykezelő delegálás
    document.addEventListener('click', function(e) {
        if (e.target && (e.target.id === 'exportStats' || e.target.closest('#exportStats'))) {
            console.log('Export gombra kattintás észlelve');
            const statTypeSelect = document.getElementById('statType');
            if (statTypeSelect) {
                let endpoint;
                switch(statTypeSelect.value) {
                    case 'package_distribution':
                        endpoint = 'getStats';
                        break;
                    case 'time_distribution':
                        endpoint = 'getTimeDistribution';
                        break;
                    case 'financial':
                        endpoint = 'getFinancialStats';
                        break;
                    case 'modification_types':
                        endpoint = 'getModificationTypes';
                        break;
                    default:
                        endpoint = 'getStats';
                }
                console.log('Exportálás: ' + endpoint);
                
                // Indítjuk az exportálást
                fetch(`subscription_details.php?action=${endpoint}&format=csv`)
                    .then(response => {
                        console.log('Válasz státusz:', response.status);
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.blob();
                    })
                    .then(blob => {
                        console.log('Blob méret:', blob.size);
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `statistics_${endpoint}.csv`;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        a.remove();
                    })
                    .catch(error => {
                        console.error('Exportálási hiba:', error);
                        showError('Hiba', 'Nem sikerült exportálni az adatokat');
                    });
            }
        }
    });
});

function loadPackageDistributionStats() {
    fetch('package_distribution.php')
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Hiba történt az adatok betöltése során');
            }

            const data = result.data;
            if (!data || !data.labels || !data.datasets || !data.datasets[0]) {
                throw new Error('Érvénytelen adatformátum');
            }

            const ctx = document.getElementById('statsChart');
            if (!ctx) {
                throw new Error('Nem található a grafikon canvas eleme');
            }
            
            // Töröljük a meglévő grafikont, ha van
            if (window.currentChart) {
                window.currentChart.destroy();
            }

            // Alapértelmezett magasság visszaállítása
            const chartContainer = document.querySelector('.chart-container');
            if (chartContainer) {
                chartContainer.style.height = '150px';
            }

            // Téma szín beállítása
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            const textColor = isDarkMode ? '#ffffff' : '#333333';

            // Létrehozzuk az új kördiagramot
            window.currentChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    ...data,
                    datasets: [{
                        ...data.datasets[0],
                        borderWidth: isDarkMode ? 0 : 2,
                        borderColor: isDarkMode ? data.datasets[0].backgroundColor : Array(data.datasets[0].data.length).fill('#ffffff')
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            left: 10,
                            right: 150,
                            top: 0,
                            bottom: 0
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            align: 'center',
                            labels: {
                                color: textColor,
                                font: {
                                    size: 12
                                },
                                padding: 10,
                                boxWidth: 15,
                                boxHeight: 2
                            }
                        },
                        title: {
                            display: true,
                            text: 'Előfizetési csomagok megoszlása',
                            color: textColor,
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 15
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 12
                            },
                            bodyFont: {
                                size: 12
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgba(255, 255, 255, 0.2)',
                            borderWidth: 1,
                            padding: 10
                        }
                    }
                }
            });

            // Frissítjük a statisztikai kártyákat
            updatePackageStatCards(data.labels, data.datasets[0].data);
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Hiba történt a statisztikák betöltése során', error.message);
        });
}

function loadTimeDistributionStats() {
    fetch('subscription_details.php?action=getTimeDistribution')
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Hiba történt az adatok betöltése során');
            }

            const data = result.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                throw new Error('Érvénytelen adatformátum');
            }

            const ctx = document.getElementById('statsChart');
            if (!ctx) {
                throw new Error('Nem található a grafikon canvas eleme');
            }
            
            // Töröljük a meglévő grafikont, ha van
            if (window.currentChart) {
                window.currentChart.destroy();
            }

            // Alapértelmezett magasság visszaállítása
            const chartContainer = document.querySelector('.chart-container');
            if (chartContainer) {
                chartContainer.style.height = '150px';
            }

            // Téma szín beállítása
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            const textColor = isDarkMode ? '#ffffff' : '#333333';

            // Létrehozzuk az új vonaldiagramot
            window.currentChart = new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            left: 10,
                            right: 150,
                            top: 10,
                            bottom: 10
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            align: 'center',
                            labels: {
                                color: textColor,
                                font: {
                                    size: 12
                                },
                                padding: 10,
                                boxWidth: 15,
                                boxHeight: 2
                            }
                        },
                        title: {
                            display: true,
                            text: 'Előfizetések időbeli eloszlása',
                            color: textColor,
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 15
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 12
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 10
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: textColor,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        y: {
                            ticks: {
                                color: textColor,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    }
                }
            });

            // Frissítjük a statisztikai kártyákat
            updateTimeDistributionStatCards(data.datasets[0].data);
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Hiba történt a statisztikák betöltése során', error.message);
        });
}

function loadFinancialStats() {
    fetch('subscription_details.php?action=getFinancialStats')
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Hiba történt az adatok betöltése során');
            }

            const data = result.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                throw new Error('Érvénytelen adatformátum');
            }

            const ctx = document.getElementById('statsChart');
            if (!ctx) {
                throw new Error('Nem található a grafikon canvas eleme');
            }
            
            // Töröljük a meglévő grafikont, ha van
            if (window.currentChart) {
                window.currentChart.destroy();
            }

            // Pénzügyi grafikonhoz növelt konténer magasság
            const chartContainer = document.querySelector('.chart-container');
            if (chartContainer) {
                chartContainer.style.height = '250px';
            }

            // Téma szín beállítása
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            const textColor = isDarkMode ? '#ffffff' : '#333333';

            // Létrehozzuk az új oszlopdiagramot
            window.currentChart = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    layout: {
                        padding: {
                            left: 10,
                            right: 150,
                            top: 20,
                            bottom: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            align: 'center',
                            labels: {
                                color: textColor,
                                font: {
                                    size: 12
                                },
                                padding: 10,
                                boxWidth: 15,
                                boxHeight: 15
                            }
                        },
                        title: {
                            display: true,
                            text: 'Pénzügyi áttekintés',
                            color: textColor,
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                bottom: 15
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 12
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 10
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: textColor,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        y: {
                            ticks: {
                                color: textColor,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    }
                }
            });

            // Frissítjük a statisztikai kártyákat
            if (data.summary) {
                updateFinancialStatCardsSummary(data.summary);
            } else {
                updateFinancialStatCards(data.datasets[0].data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Hiba történt a statisztikák betöltése során', error.message);
        });
}

function updateTimeDistributionStatCards(data) {
    // Töröljük a meglévő kártyákat
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach(card => card.remove());
    
    // Létrehozzuk a kártyákat a summaryból
    const statCards = document.querySelector('.stat-cards');
    if (!statCards) return;
    
    statCards.style.display = 'grid';
    statCards.style.gridTemplateColumns = 'repeat(auto-fit, minmax(250px, 1fr))';
    statCards.style.gap = '25px';
    statCards.style.padding = '25px';
    statCards.style.marginTop = '20px';
    statCards.style.borderTop = '1px solid var(--border-color)';
    
    // Csak egy összesítő kártyát készítünk
    const total = data.reduce((a, b) => a + b, 0);
    
    const card = document.createElement('div');
    card.className = 'stat-card';
    card.style.padding = '25px';
    card.style.borderRadius = '12px';
    card.style.backgroundColor = 'var(--card-bg)';
    card.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
    card.style.transition = 'transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out';
    card.style.marginBottom = '20px';
    card.style.position = 'relative';
    card.style.overflow = 'visible';
    card.style.minHeight = '160px';
    card.style.border = '3px solid var(--primary-color)';
    card.style.transform = 'scale(1.02)';
    
    card.innerHTML = `
        <h3 style="margin: 0 0 20px 0; font-size: 1.2em; color: var(--text-color);">Összes új előfizetés</h3>
        <div class="stat-value" style="font-size: 2em; font-weight: bold; color: var(--primary-color); margin: 15px 0;">${total}</div>
        <div class="stat-subtitle" style="font-size: 1em; color: var(--text-color);">A kiválasztott időszakban</div>
    `;
    
    // Hover effektus
    card.addEventListener('mouseover', () => {
        card.style.transform = 'scale(1.04)';
        card.style.boxShadow = '0 6px 12px rgba(0,0,0,0.2)';
    });
    
    card.addEventListener('mouseout', () => {
        card.style.transform = 'scale(1.02)';
        card.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
    });
    
    statCards.appendChild(card);
    
    // Opcionálisan hozzáadhatunk további statisztikákat is
    const currentPeriodIndex = data.length - 1;
    const previousPeriodIndex = data.length - 2;
    
    if (currentPeriodIndex >= 0 && previousPeriodIndex >= 0) {
        const currentPeriodValue = data[currentPeriodIndex];
        const previousPeriodValue = data[previousPeriodIndex];
        const growthRate = previousPeriodValue === 0 ? 100 : ((currentPeriodValue - previousPeriodValue) / previousPeriodValue * 100).toFixed(1);
        const isPositiveGrowth = growthRate >= 0;
        
        const growthCard = document.createElement('div');
        growthCard.className = 'stat-card';
        growthCard.style.padding = '25px';
        growthCard.style.borderRadius = '12px';
        growthCard.style.backgroundColor = 'var(--card-bg)';
        growthCard.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
        growthCard.style.transition = 'transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out';
        growthCard.style.marginBottom = '20px';
        growthCard.style.position = 'relative';
        growthCard.style.overflow = 'visible';
        growthCard.style.minHeight = '160px';
        
        growthCard.innerHTML = `
            <h3 style="margin: 0 0 20px 0; font-size: 1.2em; color: var(--text-color);">Növekedés</h3>
            <div class="stat-value" style="font-size: 2em; font-weight: bold; color: ${isPositiveGrowth ? 'var(--success-color)' : 'var(--error-color)'}; margin: 15px 0;">
                ${isPositiveGrowth ? '+' : ''}${growthRate}%
            </div>
            <div class="stat-subtitle" style="font-size: 1em; color: var(--text-color);">Az előző időszakhoz képest</div>
        `;
        
        // Hover effektus
        growthCard.addEventListener('mouseover', () => {
            growthCard.style.transform = 'scale(1.02)';
            growthCard.style.boxShadow = '0 6px 12px rgba(0,0,0,0.2)';
        });
        
        growthCard.addEventListener('mouseout', () => {
            growthCard.style.transform = 'scale(1)';
            growthCard.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
        });
        
        statCards.appendChild(growthCard);
    }
}

function updateFinancialStatCardsSummary(summary) {
    // Töröljük a meglévő kártyákat
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach(card => card.remove());
    
    // Töröljük a meglévő breakdownTitle elemeket is
    const existingBreakdownTitles = document.querySelectorAll('.breakdown-title');
    existingBreakdownTitles.forEach(title => title.remove());
    
    // Létrehozzuk a kártyákat a summaryból
    const statCards = document.querySelector('.stat-cards');
    statCards.style.display = 'grid';
    statCards.style.gridTemplateColumns = 'repeat(auto-fit, minmax(250px, 1fr))';
    statCards.style.gap = '25px';
    statCards.style.padding = '25px';
    statCards.style.marginTop = '20px';
    statCards.style.borderTop = '1px solid var(--border-color)';
    
    // Csak a fő összegző kártya
    const totalCard = createFinancialStatCard('Összes bevétel', summary.grand_total, null, true);
    statCards.appendChild(totalCard);
    
    // Csak ha van adat a lebontásban
    if (summary.plan_breakdown && summary.plan_breakdown.length > 0) {
        // Csomag szerinti bontás cím
        const breakdownTitle = document.createElement('div');
        breakdownTitle.className = 'breakdown-title';
        breakdownTitle.style.gridColumn = '1 / -1';
        breakdownTitle.style.marginTop = '40px';
        breakdownTitle.style.marginBottom = '10px';
        breakdownTitle.style.fontWeight = 'bold';
        breakdownTitle.style.fontSize = '1.2em';
        breakdownTitle.style.color = 'var(--text-color)';
        breakdownTitle.textContent = 'Csomag szerinti bevétel bontás';
        statCards.appendChild(breakdownTitle);
        
        // Csomag szerinti kártyák
        summary.plan_breakdown.forEach(plan => {
            // Formázott fizetési típus hozzáadása a címhez
            let titleWithBillingType = plan.plan_name;
            if (plan.billing_type) {
                const billingTypeText = plan.billing_type === 'Havi' ? '(Havi)' : '(Éves)';
                if (!titleWithBillingType.includes('Havi') && !titleWithBillingType.includes('Éves')) {
                    titleWithBillingType += ` ${billingTypeText}`;
                }
            }
            
            const planCard = createFinancialStatCard(
                titleWithBillingType, 
                Number(plan.total_revenue).toLocaleString('hu-HU') + ' Ft',
                plan.number_of_payments + ' kifizetés'
            );
            statCards.appendChild(planCard);
        });
    }
}

function createFinancialStatCard(title, value, subtitle = null, isHighlighted = false) {
    const card = document.createElement('div');
    card.className = 'stat-card';
    card.style.padding = '25px';
    card.style.borderRadius = '12px';
    card.style.backgroundColor = 'var(--card-bg)';
    card.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
    card.style.transition = 'transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out';
    card.style.marginBottom = '20px';
    card.style.position = 'relative';
    card.style.overflow = 'visible';
    card.style.minHeight = '160px';
    
    if (isHighlighted) {
        card.style.border = '3px solid var(--primary-color)';
        card.style.transform = 'scale(1.02)';
    }
    
    card.innerHTML = `
        <h3 style="margin: 0 0 20px 0; font-size: 1.2em; color: var(--text-color);">${title}</h3>
        <div class="stat-value" style="font-size: 2em; font-weight: bold; color: var(--primary-color); margin: 15px 0;">${value}</div>
        ${subtitle ? `<div class="stat-subtitle" style="font-size: 1em; color: var(--text-color);">${subtitle}</div>` : ''}
    `;
    
    // Hover effektus
    card.addEventListener('mouseover', () => {
        card.style.transform = isHighlighted ? 'scale(1.04)' : 'scale(1.02)';
        card.style.boxShadow = '0 6px 12px rgba(0,0,0,0.2)';
    });
    
    card.addEventListener('mouseout', () => {
        card.style.transform = isHighlighted ? 'scale(1.02)' : 'scale(1)';
        card.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
    });
    
    return card;
}

function updateFinancialStatCards(data) {
    const total = data.reduce((a, b) => a + b, 0);
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach((card, index) => {
        const value = data[index] || 0;
        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
        card.innerHTML = `
            <h3>${card.getAttribute('data-label') || 'Kategória ' + (index + 1)}</h3>
            <div class="stat-value">${value}</div>
            <div class="stat-percentage">${percentage}%</div>
        `;
    });
}

function createPieChart(data, labels) {
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDarkMode ? '#ffffff' : '#333333';

    const chartColors = [
        'rgb(75, 192, 192)',     // türkiz
        'rgb(54, 162, 235)',     // kék
        'rgb(153, 102, 255)',    // lila
        'rgb(255, 159, 64)'      // narancssárga
    ];

    const config = {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: chartColors,
                borderColor: isDarkMode ? chartColors : Array(data.length).fill('#ffffff'),
                borderWidth: isDarkMode ? 0 : 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: textColor,
                        font: {
                            size: 14
                        },
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const percentage = ((value / data.reduce((a, b) => a + b)) * 100).toFixed(1);
                            return `${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    };

    return config;
} 