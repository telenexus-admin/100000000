<div class="box box-solid">
    <div class="box-header">
        <i class="fa fa-inbox"></i>
        <h3 class="box-title">{Lang::T('Total Monthly Sales')}</h3>
        <div class="box-tools pull-right">
            <button type="button" class="btn bg-teal btn-sm" data-widget="collapse">
                <i class="fa fa-minus"></i>
            </button>
            <a href="{Text::url('dashboard&refresh')}" class="btn bg-teal btn-sm">
                <i class="fa fa-refresh"></i>
            </a>
        </div>
    </div>
    <div class="box-body border-radius-none">
        <canvas class="chart" id="salesChart" style="height: 250px; width: 100%;"></canvas>
    </div>
    
    <!-- Debug info (remove in production) -->
    <div class="box-footer" style="display: none;" id="debug-info">
        <pre>Monthly Sales Data: {$monthlySales|json_encode|escape}</pre>
    </div>
</div>

<!-- Make sure Chart.js is loaded -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script type="text/javascript">
    {if $_c['hide_tmc'] != 'yes'}
        document.addEventListener("DOMContentLoaded", function() {
            console.log("DOM loaded, creating chart...");
            
            try {
                // Get the data directly from PHP
                var monthlySales = {$monthlySales|json_encode nofilter};
                
                console.log("Raw monthlySales data:", monthlySales);
                console.log("Type of monthlySales:", typeof monthlySales);
                console.log("Is array?", Array.isArray(monthlySales));
                
                // If it's a string, parse it
                if (typeof monthlySales === 'string') {
                    monthlySales = JSON.parse(monthlySales);
                    console.log("Parsed monthlySales:", monthlySales);
                }
                
                // Ensure we have an array
                if (!monthlySales || !Array.isArray(monthlySales)) {
                    console.error("monthlySales is not an array:", monthlySales);
                    monthlySales = [];
                    
                    // Create empty data for all months
                    for (var i = 1; i <= 12; i++) {
                        monthlySales.push({
                            month: i,
                            totalSales: 0
                        });
                    }
                    console.log("Created empty monthlySales:", monthlySales);
                }
                
                // Month names
                var monthNames = [
                    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                ];
                
                // Prepare data for chart
                var labels = [];
                var data = [];
                
                for (var i = 1; i <= 12; i++) {
                    labels.push(monthNames[i - 1]);
                    
                    // Find data for this month
                    var found = false;
                    for (var j = 0; j < monthlySales.length; j++) {
                        if (monthlySales[j] && monthlySales[j].month === i) {
                            data.push(parseFloat(monthlySales[j].totalSales) || 0);
                            console.log("Month " + i + " (" + monthNames[i-1] + "): " + monthlySales[j].totalSales);
                            found = true;
                            break;
                        }
                    }
                    
                    if (!found) {
                        data.push(0);
                        console.log("Month " + i + " (" + monthNames[i-1] + "): 0 (no data)");
                    }
                }
                
                console.log("Final labels:", labels);
                console.log("Final data:", data);
                
                // Calculate max value for y-axis
                var maxValue = Math.max.apply(null, data);
                console.log("Max value:", maxValue);
                
                var yMax = maxValue > 0 ? Math.ceil(maxValue / 1000) * 1000 : 10000;
                console.log("Y-axis max:", yMax);
                
                // Get canvas element
                var canvas = document.getElementById('salesChart');
                if (!canvas) {
                    console.error("Canvas element not found!");
                    return;
                }
                
                console.log("Canvas found, creating line chart...");
                
                // Create LINE CHART instead of bar chart
                var ctx = canvas.getContext('2d');
                var chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Monthly Sales',
                            data: data,
                            borderColor: '#f39c12',
                            backgroundColor: 'rgba(243, 156, 18, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#f39c12',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointHoverBackgroundColor: '#f39c12',
                            pointHoverBorderColor: '#fff',
                            pointHoverBorderWidth: 2
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
                                        return 'Sales: ' + context.raw.toLocaleString();
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
                                    color: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    stepSize: yMax / 5,
                                    callback: function(value) {
                                        return value.toLocaleString();
                                    }
                                }
                            }
                        },
                        elements: {
                            line: {
                                borderJoin: 'round',
                                borderCap: 'round'
                            }
                        },
                        interaction: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                });
                
                console.log("Line chart created successfully!");
                
            } catch (e) {
                console.error("Error creating chart:", e);
                console.error("Error stack:", e.stack);
            }
        });
    {/if}
</script>