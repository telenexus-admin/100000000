<div class="box box-solid">
    <div class="box-header with-border">
        <i class="fa fa-line-chart"></i>
        <h3 class="box-title">{Lang::T('Monthly Registered Customers')}</h3>
        <div class="box-tools pull-right">
            <button type="button" class="btn bg-teal btn-sm" data-widget="collapse">
                <i class="fa fa-minus"></i>
            </button>
            <a href="{Text::url('dashboard&refresh')}" class="btn bg-teal btn-sm">
                <i class="fa fa-refresh"></i>
            </a>
        </div>
    </div>
    <div class="box-body">
        <div class="chart-container" style="position: relative; height: 250px; width: 100%;">
            <canvas id="monthlyCustomersChart"></canvas>
        </div>
        <div class="chart-legend mt-3">
            <div class="row text-center">
                <div class="col-md-4">
                    <div class="description-block">
                        <h5 class="description-header text-green" id="totalThisYear">0</h5>
                        <span class="description-text">This Year</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="description-block">
                        <h5 class="description-header text-blue" id="thisMonth">0</h5>
                        <span class="description-text">This Month</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="description-block">
                        <h5 class="description-header text-yellow" id="avgMonth">0</h5>
                        <span class="description-text">Avg/Month</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load Chart.js if not already loaded -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM loaded, creating monthly customers chart...");
    
    try {
        // Chart data from PHP - using nofilter to prevent escaping
        var monthlyData = {$monthlyRegistered|json_encode nofilter};
        
        console.log("Raw monthlyData:", monthlyData);
        console.log("Type of monthlyData:", typeof monthlyData);
        
        // Parse if it's a string
        if (typeof monthlyData === 'string') {
            monthlyData = JSON.parse(monthlyData);
            console.log("Parsed monthlyData:", monthlyData);
        }
        
        // Ensure we have an array
        if (!monthlyData || !Array.isArray(monthlyData)) {
            console.error("monthlyData is not an array:", monthlyData);
            monthlyData = [];
            
            // Create empty data for all months
            for (var i = 1; i <= 12; i++) {
                monthlyData.push({
                    month: i,
                    count: 0
                });
            }
        }
        
        var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var labels = [];
        var data = [];
        var currentMonth = new Date().getMonth() + 1;
        
        console.log("Current month:", currentMonth);
        
        // Prepare data for all 12 months
        for (var i = 1; i <= 12; i++) {
            labels.push(monthNames[i - 1]);
            
            // Find data for this month
            var monthFound = false;
            for (var j = 0; j < monthlyData.length; j++) {
                // Check for both 'month' and 'date' property names
                var monthValue = monthlyData[j].month || monthlyData[j].date;
                if (monthValue === i) {
                    data.push(parseInt(monthlyData[j].count) || 0);
                    console.log("Month " + i + " (" + monthNames[i-1] + "): " + monthlyData[j].count + " registrations");
                    monthFound = true;
                    break;
                }
            }
            
            if (!monthFound) {
                data.push(0);
                console.log("Month " + i + " (" + monthNames[i-1] + "): 0 registrations (no data)");
            }
        }
        
        console.log("Final data:", data);
        
        // Calculate statistics
        var totalThisYear = data.reduce(function(a, b) { return a + b; }, 0);
        var thisMonthCount = data[currentMonth - 1] || 0;
        var avgPerMonth = totalThisYear > 0 ? Math.round(totalThisYear / 12) : 0;
        
        console.log("Total this year:", totalThisYear);
        console.log("This month:", thisMonthCount);
        console.log("Average per month:", avgPerMonth);
        
        // Update statistics display
        document.getElementById('totalThisYear').textContent = totalThisYear;
        document.getElementById('thisMonth').textContent = thisMonthCount;
        document.getElementById('avgMonth').textContent = avgPerMonth;
        
        // Calculate max value for y-axis
        var maxValue = Math.max.apply(null, data);
        var yMax = maxValue > 0 ? Math.ceil(maxValue / 10) * 10 : 50; // Round up to nearest 10, minimum 50
        
        console.log("Max value:", maxValue, "Y-axis max:", yMax);
        
        // Get canvas context
        var canvas = document.getElementById('monthlyCustomersChart');
        if (!canvas) {
            console.error("Canvas element not found!");
            return;
        }
        
        var ctx = canvas.getContext('2d');
        
        // Create gradient
        var gradient = ctx.createLinearGradient(0, 0, 0, 250);
        gradient.addColorStop(0, 'rgba(54, 162, 235, 0.8)');
        gradient.addColorStop(1, 'rgba(54, 162, 235, 0.2)');
        
        // Create chart
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Registered Members',
                    data: data,
                    backgroundColor: gradient,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Registered Members: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666',
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        max: yMax,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: Math.ceil(yMax / 5),
                            color: '#666',
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                return Number.isInteger(value) ? value : '';
                            }
                        }
                    }
                }
            }
        });
        
        // Highlight current month
        var backgroundColors = new Array(12).fill(gradient);
        backgroundColors[currentMonth - 1] = 'rgba(255, 193, 7, 0.8)';
        chart.data.datasets[0].backgroundColor = backgroundColors;
        chart.update();
        
        console.log("Monthly customers chart created successfully!");
        
    } catch (e) {
        console.error("Error creating monthly customers chart:", e);
        console.error("Error stack:", e.stack);
    }
});
</script>

<style>
.chart-container {
    position: relative;
}
.description-block {
    margin: 10px 0;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}
.description-header {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
    line-height: 1.2;
}
.description-text {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    display: block;
    margin-top: 5px;
}
.text-green { color: #00a65a !important; }
.text-blue { color: #3c8dbc !important; }
.text-yellow { color: #f39c12 !important; }
</style>