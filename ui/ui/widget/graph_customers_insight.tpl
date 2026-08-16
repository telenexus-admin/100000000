<div class="panel panel-info panel-hovered mb20 activities">
    <div class="panel-heading">{Lang::T('All Users Insights')}</div>
    <div class="panel-body">
        <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start;">
            <div style="flex:1 1 180px;max-width:240px;">
                <canvas id="userRechargesChart"></canvas>
            </div>
            <div style="flex:2 1 300px;max-width:560px;">
                <canvas id="planPerformanceChart"></canvas>
            </div>
        </div>
    </div>
</div>


<script type="text/javascript">
            {literal}
        document.addEventListener("DOMContentLoaded", function() {
            // parse template variables
            var u_act = parseInt('{/literal}{$u_act}{literal}') || 0;
            var c_all = parseInt('{/literal}{$c_all}{literal}') || 0;
            var u_all = parseInt('{/literal}{$u_all}{literal}') || 0;
            var expired = Math.max(0, u_all - u_act);
            var inactive = Math.max(0, c_all - u_all);

            // Small doughnut chart config for user insight
            var userData = {
                labels: ['Active', 'Expired', 'Inactive'],
                datasets: [{
                    data: [u_act, expired, inactive],
                    backgroundColor: ['#04BF0D', '#BF2304', '#3B82F6'],
                    hoverOffset: 6,
                }]
            };

            var userOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                    tooltip: { enabled: true }
                }
            };

            // Plan performance (top 10) - horizontal bar
            var planLabels = [];
            var planCounts = [];
            try {
                planLabels = JSON.parse('{/literal}{$plan_perf_labels|default:'[]'}{literal}');
                planCounts = JSON.parse('{/literal}{$plan_perf_counts|default:'[]'}{literal}');
            } catch (e) {
                planLabels = [];
                planCounts = [];
                console && console.error && console.error('plan performance JSON parse error', e);
            }

            var planData = {
                labels: planLabels,
                datasets: [{
                    label: 'Recharges',
                    data: planCounts,
                    backgroundColor: planLabels.map((_, i) => 'rgba(59,130,246,' + (0.6 - Math.min(i * 0.03, 0.4)) + ')'),
                    borderColor: planLabels.map(() => 'rgba(59,130,246,1)'),
                    borderWidth: 1
                }]
            };

            var planOptions = {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                    y: { ticks: { autoSkip: true, maxRotation: 0, minRotation: 0 } }
                },
                plugins: { legend: { display: false }, tooltip: { enabled: true } }
            };

            // Render charts with smaller fixed heights
            var userCtx = document.getElementById('userRechargesChart').getContext('2d');
            document.getElementById('userRechargesChart').parentElement.style.height = '220px';
            var userChart = new Chart(userCtx, { type: 'doughnut', data: userData, options: userOptions });

            var planCtx = document.getElementById('planPerformanceChart').getContext('2d');
            document.getElementById('planPerformanceChart').parentElement.style.height = '220px';
            var planChart = new Chart(planCtx, { type: 'bar', data: planData, options: planOptions });
        });
    {/literal}
</script>