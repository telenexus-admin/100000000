{include file="sections/header.tpl"}

<div class="container my-5">
    <!-- Title -->
    <div class="text-center mb-5">
        <h2 class="fw-bold display-5 text-primary">💰 Income Progress Dashboard</h2>
        <p class="lead text-muted">Track your earnings daily, weekly, and monthly. Instant insights, clear visuals.</p>
    </div>

    <!-- Earnings Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card shadow-lg border-0 h-100 bg-success bg-gradient text-white text-center">
                <div class="card-body">
                    <h5 class="card-title fw-bold">🔥 Today vs Yesterday</h5>
                    <p class="display-6 fw-bold">Today: Ksh {$earnings.today|default:0}</p>
                    <p class="text-light">Yesterday: Ksh {$earnings.yesterday|default:0}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-lg border-0 h-100 bg-primary bg-gradient text-white text-center">
                <div class="card-body">
                    <h5 class="card-title fw-bold">📅 This Week vs Last Week</h5>
                    <p class="display-6 fw-bold">This Week: Ksh {$earnings.this_week|default:0}</p>
                    <p class="text-light">Last Week: Ksh {$earnings.last_week|default:0}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-lg border-0 h-100 text-white text-center" style="background: linear-gradient(45deg,#6f42c1,#a78bfa);">
                <div class="card-body">
                    <h5 class="card-title fw-bold">📆 This Month vs Last Month</h5>
                    <p class="display-6 fw-bold">This Month: Ksh {$earnings.this_month|default:0}</p>
                    <p class="text-light">Last Month: Ksh {$earnings.last_month|default:0}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphs Section -->
    <div class="row g-4">
        <div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">📊 Daily Earnings</h5><canvas id="dailyChart"></canvas></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">📈 Weekly Earnings</h5><canvas id="weeklyChart"></canvas></div></div></div>
        <div class="col-md-12"><div class="card"><div class="card-body"><h5 class="card-title">📉 Monthly Earnings</h5><canvas id="monthlyChart"></canvas></div></div></div>

        <div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">🖧 Transactions by Router</h5><canvas id="routerChart"></canvas></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">📐 Avg Transaction Value (Daily)</h5><canvas id="avgChart"></canvas></div></div></div>

        <div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">💡 Revenue by Service Plan</h5><canvas id="planChart"></canvas></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">🏆 Top 10 Customers</h5><canvas id="customersChart"></canvas></div></div></div>

        <div class="col-md-12"><div class="card"><div class="card-body"><h5 class="card-title">⏰ Peak Transaction Hours</h5><canvas id="hoursChart"></canvas></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">💳 Payment Methods</h5><canvas id="methodChart"></canvas></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">👥 Customers Breakdown (30 Days)</h5><canvas id="customerTypeChart"></canvas></div></div></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
{literal}
<script>
const chartOptions = {responsive:true,plugins:{legend:{display:true,position:'bottom'}},scales:{y:{beginAtZero:true}}};

// Daily / Weekly / Monthly
new Chart(document.getElementById('dailyChart'), {
    type:'bar',
    data:{labels:['Yesterday','Today'],datasets:[{data:[{/literal}{$earnings.yesterday|default:0}{literal},{/literal}{$earnings.today|default:0}{literal}],backgroundColor:['#34d399','#10b981'],borderRadius:6}]},
    options:chartOptions
});

new Chart(document.getElementById('weeklyChart'), {
    type:'bar',
    data:{labels:['Last Week','This Week'],datasets:[{data:[{/literal}{$earnings.last_week|default:0}{literal},{/literal}{$earnings.this_week|default:0}{literal}],backgroundColor:['#60a5fa','#3b82f6'],borderRadius:6}]},
    options:chartOptions
});

new Chart(document.getElementById('monthlyChart'), {
    type:'bar',
    data:{labels:['Last Month','This Month'],datasets:[{data:[{/literal}{$earnings.last_month|default:0}{literal},{/literal}{$earnings.this_month|default:0}{literal}],backgroundColor:['#c084fc','#a78bfa'],borderRadius:6}]},
    options:chartOptions
});

// Router Pie
new Chart(document.getElementById('routerChart'), {
    type:'pie',
    data:{
        labels:[{/literal}{foreach $routerData as $r}'{$r.routers}',{/foreach}{literal}],
        datasets:[{data:[{/literal}{foreach $routerData as $r}{$r.total},{/foreach}{literal}],backgroundColor:['#f87171','#60a5fa','#34d399','#facc15','#a855f7']}]
    }
});

// Avg Daily Line
new Chart(document.getElementById('avgChart'), {
    type:'line',
    data:{
        labels:[{/literal}{foreach $avgDaily as $d}'{$d.recharged_on}',{/foreach}{literal}],
        datasets:[{data:[{/literal}{foreach $avgDaily as $d}{$d.avg_value},{/foreach}{literal}],fill:false,borderColor:'#f59e0b'}]
    }
});

// Plan Revenue Pie
new Chart(document.getElementById('planChart'), {
    type:'pie',
    data:{
        labels:[{/literal}{foreach $planRevenue as $p}'{$p.plan_name}',{/foreach}{literal}],
        datasets:[{data:[{/literal}{foreach $planRevenue as $p}{$p.total},{/foreach}{literal}],backgroundColor:['#22c55e','#3b82f6','#a855f7','#f97316','#eab308']}]
    }
});

// Top Customers Bar
new Chart(document.getElementById('customersChart'), {
    type:'bar',
    data:{
        labels:[{/literal}{foreach $topCustomers as $c}'{$c.username}',{/foreach}{literal}],
        datasets:[{data:[{/literal}{foreach $topCustomers as $c}{$c.total},{/foreach}{literal}],backgroundColor:'#3b82f6'}]
    },
    options:chartOptions
});

// Peak Hours
new Chart(document.getElementById('hoursChart'), {
    type:'bar',
    data:{
        labels:[{/literal}{foreach $peakHours as $h}'{$h.hour}:00',{/foreach}{literal}],
        datasets:[{data:[{/literal}{foreach $peakHours as $h}{$h.total},{/foreach}{literal}],backgroundColor:(ctx)=>ctx.raw<Math.max(...ctx.chart.data.datasets[0].data)*0.5?'#ef4444':'#22c55e'}]
    },
    options:chartOptions
});

// Payment Methods
new Chart(document.getElementById('methodChart'), {
    type:'pie',
    data:{
        labels:[{/literal}{foreach $paymentMethods as $m}'{$m.method}',{/foreach}{literal}],
        datasets:[{data:[{/literal}{foreach $paymentMethods as $m}{$m.total},{/foreach}{literal}],backgroundColor:['#10b981','#3b82f6','#f97316','#eab308','#a855f7']}]
    }
});

// Customers Breakdown
new Chart(document.getElementById('customerTypeChart'), {
    type:'pie',
    data:{
        labels:['New','Returning','Frequent','Loyal'],
        datasets:[{data:[{/literal}{$customers.new|default:0}{literal},{/literal}{$customers.returning|default:0}{literal},{/literal}{$customers.frequent|default:0}{literal},{/literal}{$customers.loyal|default:0}{literal}],backgroundColor:['#facc15','#3b82f6','#34d399','#a855f7']}]
    }
});
</script>
{/literal}

{include file="sections/footer.tpl"}
