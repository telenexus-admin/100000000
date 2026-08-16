{include file="sections/header.tpl"}

<style>
{literal}
/* ===== DATA USAGE CUSTOM STYLES ===== */
.mm-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    transition: box-shadow 0.2s ease;
}
.mm-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

.mm-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.2s ease;
}
.mm-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.mm-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-from), var(--accent-to));
}

.mm-tab-btn {
    padding: 10px 20px;
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.mm-tab-btn:hover { color: #2563eb; }
.mm-tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }

.mm-table { width: 100%; border-collapse: collapse; }
.mm-table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.mm-table thead th.sortable { cursor: pointer; user-select: none; }
.mm-table thead th.sortable:hover { background: #f1f5f9; color: #2563eb; }
.mm-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.mm-table tbody tr:hover { background: #f8fafc; }
.mm-table tbody td { padding: 11px 16px; font-size: 13px; color: #334155; vertical-align: middle; }
.mm-table tbody tr:last-child { border-bottom: none; }

.mm-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.mm-badge-success { background: #dcfce7; color: #15803d; }

/* Traffic button */
.mm-btn-traffic {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.mm-btn-traffic:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(37,99,235,0.3);
}
.mm-btn-traffic i { font-size: 14px; }
#trafficModal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15,23,42,0.55);
    backdrop-filter: blur(3px);
    align-items: center;
    justify-content: center;
}
#trafficModal.open { display: flex; }
.tm-card {
    background: #fff;
    border-radius: 16px;
    width: 480px;
    max-width: 95vw;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    overflow: hidden;
    animation: tmSlideIn 0.25s ease;
}
@keyframes tmSlideIn {
    from { opacity:0; transform: translateY(-20px) scale(0.97); }
    to   { opacity:1; transform: translateY(0) scale(1); }
}

@media (max-width: 480px) {
    #trafficModal.open {
        align-items: flex-end;
    }
    .tm-card {
        width: 100%;
        max-width: 100%;
        border-radius: 16px 16px 0 0;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        max-height: 90vh;
        overflow-y: auto;
        overscroll-behavior: contain;
        animation: tmSlideUp 0.3s cubic-bezier(0.32, 0.72, 0, 1);
    }
    @keyframes tmSlideUp {
        from { transform: translateY(100%); }
        to   { transform: translateY(0); }
    }
    .tm-speed-value {
        font-size: 16px;
        word-break: break-all;
        overflow-wrap: anywhere;
    }
    .tm-speed-box {
        padding: 10px 8px;
    }
    .tm-body {
        padding: 14px;
    }
    .tm-header {
        padding: 14px 16px;
    }
    .tm-header h3 {
        font-size: 14px;
    }
    .tm-footer {
        padding: 10px 16px;
    }
}

body.tm-modal-open {
    overflow: hidden;
    touch-action: none;
}
.tm-header {
    background: linear-gradient(135deg, #1e40af, #2563eb);
    padding: 18px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #fff;
}
.tm-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
.tm-header p  { margin: 2px 0 0; font-size: 12px; opacity: 0.8; }
.tm-close {
    background: rgba(255,255,255,0.15);
    border: none;
    color: #fff;
    width: 32px; height: 32px;
    border-radius: 8px;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}
.tm-close:hover { background: rgba(255,255,255,0.3); }
.tm-body { padding: 22px; }
.tm-speed-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 16px;
}
.tm-speed-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.tm-speed-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}
.tm-speed-box.upload::before   { background: linear-gradient(90deg, #10b981, #059669); }
.tm-speed-box.download::before { background: linear-gradient(90deg, #2563eb, #1d4ed8); }
.tm-speed-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.tm-speed-value {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
    min-height: 28px;
    word-break: break-all;
    overflow-wrap: anywhere;
}
.tm-speed-unit { font-size: 12px; font-weight: 500; color: #64748b; margin-top: 2px; }
.tm-progress-wrap { margin-bottom: 14px; }

@media (max-width: 480px) {
    .tm-card {
        width: 100%;
        max-width: 100%;
        border-radius: 16px 16px 0 0;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
    }
    #trafficModal.open {
        align-items: flex-end;
    }
    .tm-speed-value {
        font-size: 16px;
    }
    .tm-speed-box {
        padding: 10px 8px;
    }
    .tm-body {
        padding: 14px;
    }
    .tm-header {
        padding: 14px 16px;
    }
    .tm-header h3 {
        font-size: 14px;
    }
    .tm-footer {
        padding: 10px 16px;
    }
    .tm-chart-shell {
        height: 170px;
    }
    .tm-chart-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
.tm-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
}
.tm-progress-bar {
    height: 8px;
    background: #e2e8f0;
    border-radius: 99px;
    overflow: hidden;
}
.tm-progress-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 0.5s ease;
}
.tm-progress-fill.upload   { background: linear-gradient(90deg, #10b981, #059669); }
.tm-progress-fill.download { background: linear-gradient(90deg, #2563eb, #1d4ed8); }
.tm-chart-card {
    margin: 18px 0 14px;
    background: linear-gradient(180deg, #f8fafc, #ffffff);
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
}
.tm-chart-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.tm-chart-title {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}
.tm-chart-subtitle {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}
.tm-chart-legend {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.tm-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #64748b;
    font-weight: 600;
}
.tm-legend-swatch {
    width: 10px;
    height: 10px;
    border-radius: 999px;
}
.tm-chart-shell {
    position: relative;
    height: 190px;
    border-radius: 12px;
    overflow: hidden;
    background:
        linear-gradient(to bottom, rgba(148,163,184,0.12) 1px, transparent 1px) 0 0/100% 25%,
        linear-gradient(to right, rgba(148,163,184,0.08) 1px, transparent 1px) 0 0/12.5% 100%,
        #fff;
    border: 1px solid #e2e8f0;
}
.tm-chart-ylabels {
    position: absolute;
    left: 10px;
    top: 10px;
    bottom: 26px;
    width: 48px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    pointer-events: none;
}
.tm-chart-ylabels span {
    font-size: 10px;
    color: #94a3b8;
    line-height: 1;
}
.tm-chart-svg {
    position: absolute;
    inset: 8px 8px 24px 56px;
    width: calc(100% - 64px);
    height: calc(100% - 32px);
}
.tm-chart-xlabels {
    position: absolute;
    left: 56px;
    right: 8px;
    bottom: 6px;
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 10px;
    color: #94a3b8;
}
.tm-chart-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #94a3b8;
}
.tm-chart-stats {
    margin-top: 12px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}
.tm-mini-stat {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 12px;
}
.tm-mini-stat-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #94a3b8;
    font-weight: 700;
    margin-bottom: 4px;
}
.tm-mini-stat-value {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}
.tm-footer {
    border-top: 1px solid #f1f5f9;
    padding: 14px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    color: #94a3b8;
}
.tm-status-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #22c55e;
    display: inline-block;
    margin-right: 5px;
    animation: tmPulse 1.2s infinite;
}
@keyframes tmPulse {
    0%,100% { opacity:1; }
    50%      { opacity:0.4; }
}
.mm-badge-danger  { background: #fee2e2; color: #b91c1c; }
.mm-badge-warning { background: #fef3c7; color: #92400e; }
.mm-badge-info    { background: #dbeafe; color: #1d4ed8; }
.mm-badge-gray    { background: #f1f5f9; color: #475569; }

.mm-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
}
.mm-btn-primary { background: #2563eb; color: white; }
.mm-btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
.mm-btn-danger  { background: #ef4444; color: white; }
.mm-btn-danger:hover  { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,0.3); }
.mm-btn-warning { background: #f59e0b; color: white; }
.mm-btn-warning:hover { background: #d97706; transform: translateY(-1px); }
.mm-btn-ghost   { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.mm-btn-ghost:hover { background: #e2e8f0; color: #334155; }
.mm-btn-sm { padding: 5px 10px; font-size: 12px; }
.mm-btn-xs { padding: 3px 8px; font-size: 11px; border-radius: 6px; }

.mm-input {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    color: #334155;
    background: #ffffff;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}
.mm-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.mm-select {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    color: #334155;
    background: #ffffff;
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s;
}
.mm-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.mm-label { font-size: 12px; font-weight: 500; color: #64748b; margin-bottom: 5px; display: block; }

.mm-progress-bar {
    height: 6px;
    background: #f1f5f9;
    border-radius: 999px;
    overflow: hidden;
}
.mm-progress-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.6s ease;
}

.mm-section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
}

.mm-history-filter-shell {
    padding: 18px 20px 20px;
    display: grid;
    gap: 14px;
}

.mm-history-filter-grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr 1fr;
    gap: 12px;
}

.mm-history-filter-grid.range-mode {
    grid-template-columns: 1.15fr 1fr 1fr;
}

.mm-history-field {
    display: grid;
    gap: 6px;
}

.mm-history-field .mm-label {
    margin-bottom: 0;
    font-size: 11px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}

.mm-history-control {
    min-height: 44px;
    border-radius: 10px;
    background: #fff;
}

.mm-history-search-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: end;
}

.mm-history-searchbox {
    display: grid;
    gap: 6px;
}

.mm-history-searchbox .mm-label {
    margin-bottom: 0;
    font-size: 11px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.mm-history-search-input {
    position: relative;
}

.mm-history-search-input i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13px;
}

.mm-history-search-input .mm-input {
    padding: 11px 14px 11px 36px;
    min-height: 44px;
    border-radius: 10px;
}

.mm-history-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.mm-history-actions .mm-btn {
    min-height: 44px;
    padding: 0 18px;
    font-weight: 600;
    border-radius: 10px;
}

.mm-history-quickbar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding-top: 2px;
}

.mm-history-quick-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
    margin-right: 2px;
}

.mm-history-chip {
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 12px;
    border: 1px solid #dbe3ef;
    background: #f8fafc;
    color: #475569;
}

.mm-history-chip:hover {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}

.mm-history-filter-card {
    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
}

.mm-history-filter-divider {
    height: 1px;
    background: linear-gradient(90deg, rgba(226,232,240,0) 0%, rgba(226,232,240,1) 15%, rgba(226,232,240,1) 85%, rgba(226,232,240,0) 100%);
}

/* Header actions responsive */
@media (max-width: 480px) {
    .mm-page-header { flex-wrap: nowrap !important; gap: 8px !important; align-items: center !important; }
    .mm-header-desktop { display: none !important; }
    .mm-header-mobile  { display: flex !important; }
    .mm-router-selector { min-width: 0 !important; }
    .mm-router-selector select { min-width: unset !important; max-width: 180px !important; }

    /* Update modal — slide from bottom */
    #mikromonUpdateModal {
        align-items: flex-end !important;
    }
    #mikromonUpdateModal > div {
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 16px 16px 0 0 !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        max-height: 90vh !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        animation: tmSlideUp 0.3s cubic-bezier(0.32, 0.72, 0, 1) !important;
    }

    /* Uninstall modal — slide from bottom */
    #mikromonUninstallModal {
        align-items: flex-end !important;
    }
    #mikromonUninstallModal > div {
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 16px 16px 0 0 !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        max-height: 90vh !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        animation: tmSlideUp 0.3s cubic-bezier(0.32, 0.72, 0, 1) !important;
    }
}

@media (max-width: 900px) {
    .mm-history-filter-grid,
    .mm-history-filter-grid.range-mode,
    .mm-history-search-row {
        grid-template-columns: 1fr;
    }

    .mm-history-actions {
        justify-content: stretch;
    }

    .mm-history-actions .mm-btn {
        flex: 1 1 160px;
        justify-content: center;
    }
}
.mm-section-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}
.mm-section-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
}

/* Traffic colors */
.mm-upload   { color: #16a34a; font-weight: 600; }
.mm-download { color: #2563eb; font-weight: 600; }
.mm-usage    { color: #7c3aed; font-weight: 600; }

/* Indicator dots */
.mm-dot-high   { color: #ef4444; }
.mm-dot-medium { color: #f59e0b; }
.mm-dot-low    { color: #22c55e; }
.mm-dot-none   { color: #cbd5e1; }

/* IP Link */
.mm-ip-link {
    color: #2563eb;
    text-decoration: none;
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    padding: 2px 6px;
    background: #eff6ff;
    border-radius: 4px;
    transition: all 0.2s;
}
.mm-ip-link:hover { background: #dbeafe; color: #1d4ed8; }

/* Mobile card */
.mm-mobile-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.mm-mobile-card-header {
    background: #f8fafc;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
}
.mm-mobile-card-body { padding: 12px 16px; }
.mm-mobile-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}
.mm-mobile-row:last-child { border-bottom: none; }
.mm-mobile-label { color: #64748b; font-weight: 500; }
.mm-mobile-value { color: #1e293b; text-align: right; }

/* Notification */
.mm-notification {
    position: fixed;
    top: 20px; right: 20px;
    min-width: 300px; max-width: 380px;
    padding: 14px 18px;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    z-index: 99999;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideInRight 0.3s ease;
}
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0);    opacity: 1; }
}
.mm-notification-success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
.mm-notification-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }
.mm-notification-warning { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
.mm-notification-info    { background: #eff6ff; border: 1px solid #93c5fd; color: #1d4ed8; }

/* Modal overlay */
.mm-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9998;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.mm-modal {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    animation: modalIn 0.25s ease;
}
@keyframes modalIn {
    from { transform: scale(0.95) translateY(-10px); opacity: 0; }
    to   { transform: scale(1) translateY(0); opacity: 1; }
}
.mm-modal-header {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.mm-modal-header h4 { color: white; margin: 0; font-size: 16px; font-weight: 600; }
.mm-modal-close {
    color: rgba(255,255,255,0.8);
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.2s;
}
.mm-modal-close:hover { color: white; }
.mm-modal-body { padding: 24px; }

/* Access choice modal */
.mm-access-card {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}
.mm-access-card:hover { border-color: #2563eb; background: #f8fafc; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.1); }
.mm-access-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.mm-access-icon-local  { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; }
.mm-access-icon-public { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
.mm-access-title { font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 2px; }
.mm-access-desc  { font-size: 12px; color: #64748b; }
.mm-access-url   { font-size: 12px; color: #2563eb; font-family: monospace; margin-top: 4px; }
.mm-access-action { margin-left: auto; }

/* Pagination */
.mm-pagination-btn {
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.15s;
    min-width: 32px;
    text-align: center;
}
.mm-pagination-btn:hover:not(:disabled) { background: #f1f5f9; border-color: #94a3b8; }
.mm-pagination-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
.mm-pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Sort indicator */
.mm-sort-asc::after  { content: ' ↑'; color: #2563eb; }
.mm-sort-desc::after { content: ' ↓'; color: #2563eb; }

/* Responsive helpers */
@media (max-width: 768px) {
    .mm-desktop-only { display: none !important; }
    .mm-mobile-only  { display: block !important; }
}
@media (min-width: 769px) {
    .mm-desktop-only { display: block !important; }
    .mm-mobile-only  { display: none !important; }
    .mm-desktop-flex { display: flex !important; }
}

/* Pulse animation */
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }
.mm-pulse { animation: pulse 2s infinite; }

/* Spinner */
@keyframes spin { to { transform: rotate(360deg); } }
.mm-spin { animation: spin 0.8s linear infinite; display: inline-block; }

/* NAT table */
.mm-nat-table th { background: #f8fafc; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
.mm-nat-table td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
.mm-nat-table tr:last-child td { border-bottom: none; }
.mm-nat-table tr:hover td { background: #f8fafc; }
{/literal}

/* ===== RESPONSIVE — Mobile First ===== */
@media (max-width: 768px) {
    .mm-desktop-only { display: none !important; }
    .mm-mobile-only  { display: block !important; }

    /* Main content padding lebih kecil di mobile */
    .mm-main-content { padding: 12px !important; }

    /* Page header stack vertikal */
    .mm-page-header { flex-direction: column; align-items: flex-start !important; gap: 10px !important; padding: 12px 16px !important; }
    .mm-router-selector { width: 100%; }
    .mm-router-selector select { width: 100% !important; min-width: unset !important; }

    /* Tab nav scroll horizontal */
    .mm-tab-nav { padding: 0 12px !important; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
    .mm-tab-btn { font-size: 13px !important; padding: 10px 14px !important; }

    /* Stat cards: 2 kolom di mobile */
    .mm-stat-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; }
    .mm-stat-card { padding: 10px 12px !important; }
    .mm-stat-card .mm-stat-value { font-size: 18px !important; }

    /* Kecilkan semua elemen di dalam stat card untuk mobile */
    .mm-stat-card div[style*="font-size: 28px"] { font-size: 20px !important; }
    .mm-stat-card div[style*="font-size: 11px"] { font-size: 10px !important; }
    .mm-stat-card div[style*="font-size: 16px"] { font-size: 13px !important; }
    .mm-stat-card div[style*="font-size: 18px"] { font-size: 14px !important; }

    /* Kecilkan icon box */
    .mm-stat-card div[style*="width: 40px"] { width: 30px !important; height: 30px !important; font-size: 14px !important; border-radius: 8px !important; flex-shrink: 0; }

    /* Uptime detail — potong teks panjang */
    #uptime-detail { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 80px; }

    /* Memory detail — potong teks panjang */
    #memory-detail { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 80px; }

    /* Bandwidth card — font upload/download lebih kecil */
    #bandwidth-upload, #bandwidth-download { font-size: 12px !important; }

    /* Tools cards: 1 kolom di mobile */
    .mm-tools-grid { grid-template-columns: 1fr !important; gap: 12px !important; }

    /* Table section toolbar wrap */
    .mm-table-toolbar { flex-wrap: wrap; gap: 8px !important; }
    .mm-search-input { width: 100% !important; }

    /* Modal full screen di mobile */
    .mm-modal { max-width: 100% !important; margin: 0 !important; border-radius: 16px 16px 0 0 !important; position: fixed !important; bottom: 0 !important; }
    .mm-modal-overlay { align-items: flex-end !important; padding: 0 !important; }

    /* History tab mobile */
    .mm-history-stat-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; }
    #historyPaginationInfo { font-size: 11px !important; }
    #historyPaginationButtons { justify-content: center !important; }
}

@media (min-width: 769px) and (max-width: 1024px) {
    /* Tablet: stat cards 3 kolom */
    .mm-stat-grid { grid-template-columns: repeat(3, 1fr) !important; }

    /* Tools cards: 1 kolom kalau layar pas-pasan */
    .mm-tools-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important; }

    .mm-main-content { padding: 16px !important; }
}

@media (min-width: 769px) {
    .mm-desktop-only { display: block !important; }
    .mm-mobile-only  { display: none !important; }
    .mm-desktop-flex { display: flex !important; }
}

/* ===== TRAFFIC MODAL: SPEED DISPLAY ===== */
.tm-speed-main {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
.tm-speed-primary {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.tm-speed-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}
.tm-speed-primary.upload::before {
    background: linear-gradient(90deg, #10b981, #059669);
}
.tm-speed-primary.download::before {
    background: linear-gradient(90deg, #2563eb, #1d4ed8);
}
.tm-speed-primary:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.tm-speed-icon {
    font-size: 28px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.tm-speed-primary.upload .tm-speed-icon {
    color: #10b981;
}
.tm-speed-primary.download .tm-speed-icon {
    color: #2563eb;
}
.tm-speed-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #64748b;
}
.tm-speed-value {
    font-size: 32px;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
    word-break: break-all;
    overflow-wrap: anywhere;
}

/* ===== TRAFFIC MODAL: CHART SUMMARY ===== */
.tm-chart-summary {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 12px;
}
.tm-summary-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.tm-summary-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    color: #94a3b8;
}
.tm-summary-value {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}

@media (max-width: 480px) {
    .tm-speed-main {
        gap: 12px;
        margin-bottom: 18px;
    }
    .tm-speed-primary {
        padding: 18px;
        gap: 10px;
    }
    .tm-speed-icon {
        font-size: 24px;
    }
    .tm-speed-value {
        font-size: 26px;
    }
    .tm-chart-card {
        margin: 14px 0 10px;
    }
    .tm-chart-summary {
        gap: 8px;
    }
    .tm-summary-item {
        padding: 10px;
    }
}
</style>

{if $no_router}
<!-- ===== NO ROUTER STATE ===== -->
<div style="padding: 40px 20px; text-align: center;">
    <div class="mm-card" style="max-width: 480px; margin: 0 auto; padding: 48px 40px;">
        <div style="width: 72px; height: 72px; background: #fef3c7; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px;">
            ⚠️
        </div>
        <h3 style="font-size: 20px; font-weight: 700; color: #1e293b; margin: 0 0 8px;">Router Configuration Required</h3>
        <p style="color: #64748b; font-size: 14px; margin: 0 0 28px; line-height: 1.6;">Data Usage requires at least one MikroTik router to be configured. Please add a router first before using this monitoring tool.</p>
        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a href="{$_url}routers/add" class="mm-btn mm-btn-primary">
                <i class="fa fa-plus"></i> Add Router
            </a>
            <a href="{$_url}routers/list" class="mm-btn mm-btn-ghost">
                <i class="fa fa-list"></i> Router List
            </a>
        </div>
    </div>
</div>

{else}

<!-- ===== MAIN DATA USAGE UI ===== -->
<div style="background: #f8fafc; min-height: 100vh; padding: 0;">

    <!-- Page Header -->
    <div class="mm-page-header" style="background: white; border-bottom: 1px solid #e2e8f0; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #2563eb, #7c3aed); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                <i class="fa fa-sitemap"></i>
            </div>
            <div>
                <h1 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Data Usage</h1>
                <p style="margin: 0; font-size: 12px; color: #64748b;">MikroTik Data Usage Monitor</p>
            </div>
        </div>
        <!-- Right side: Router selector + actions -->
        <div style="display:flex; align-items:center; gap:16px; flex:1; justify-content:flex-end; min-width:0;">
            <!-- Dashboard Stats -->
            <div style="display: flex; align-items: center; gap: 12px; margin-left: auto;">
                <span id="last-update-status" style="font-size: 11px; color: #94a3b8; display: flex; align-items: center; gap: 4px;">
                    <i class="fa fa-clock-o"></i> <span id="dashboard-last-update">Waiting for first sync...</span>
                </span>
            </div>

            <!-- Router Selector -->
            <div class="mm-router-selector" style="display: flex; align-items: center; gap: 8px; min-width:0; max-width:260px;">
                <label style="font-size: 12px; color: #64748b; white-space: nowrap;"><i class="fa fa-server"></i> Router:</label>
                <select class="mm-select" id="routerSelect" onchange="switchRouter()" style="width: auto; min-width: 140px; background-color: #f8fafc; border: 1px solid #e2e8f0; font-weight: 600;">
                    {foreach $routers as $r}
                        <option value="{$r['id']}" {if $r['id']==$router}selected{/if}>
                            {$r['name']}
                        </option>
                    {/foreach}
                </select>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="mm-tab-nav" style="background: white; border-bottom: 1px solid #e2e8f0; padding: 0 24px; display: flex; gap: 4px;">
        <button class="mm-tab-btn active" id="tab-dashboard" onclick="switchTab('dashboard')">
            <i class="fa fa-dashboard"></i> Dashboard
        </button>
        <button class="mm-tab-btn" id="tab-history" onclick="switchTab('history')">
            <i class="fa fa-history"></i> Usage History
        </button>
       
    </div>

    <!-- Main Content -->
    <div class="mm-main-content" style="padding: 20px 24px;">

        <!-- ===== DASHBOARD TAB ===== -->
        <div id="content-dashboard">

            <!-- STAT CARDS ROW -->
            <div class="mm-stat-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 20px;">

                <!-- Hotspot Active -->
                <div class="mm-stat-card" style="--accent-from:#3b82f6;--accent-to:#2563eb;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Hotspot Active</div>
                            <div style="font-size: 28px; font-weight: 700; color: #1e293b;" id="pppoe-count-live">—</div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 4px;" id="hotspot-status-detail">—</div>
                        </div>
                        <div style="width: 40px; height: 40px; background: #eff6ff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #3b82f6;">
                            <i class="fa fa-users"></i>
                        </div>
                    </div>
                </div>

                <!-- PPPoE Active -->
                <div class="mm-stat-card" style="--accent-from:#ef4444;--accent-to:#dc2626;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">PPPoE Active</div>
                            <div style="font-size: 28px; font-weight: 700; color: #1e293b;" id="pppoe-count-live-2">—</div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 4px;" id="pppoe-status-detail">—</div>
                        </div>
                        <div style="width: 40px; height: 40px; background: #fef2f2; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #ef4444;">
                            <i class="fa fa-plug"></i>
                        </div>
                    </div>
                </div>

                <!-- CPU Load -->
                <div class="mm-stat-card" style="--accent-from:#f59e0b;--accent-to:#d97706;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div>
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">CPU Load</div>
                            <div style="font-size: 28px; font-weight: 700; color: #1e293b;" id="cpu-load">—</div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 4px;" id="cpu-temp">Memory: <span id="memory-usage">—</span></div>
                        </div>
                        <div style="width: 40px; height: 40px; background: #fffbeb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #f59e0b;">
                            <i class="fa fa-server"></i>
                        </div>
                    </div>
                </div>

                <!-- Uptime -->
                <div class="mm-stat-card" style="--accent-from:#06b6d4;--accent-to:#0891b2;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                        <div style="flex: 1;">
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Uptime</div>
                            <div style="font-size: 28px; font-weight: 700; color: #1e293b;" id="uptime-display">—</div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 4px;" id="uptime-detail">System online</div>
                        </div>
                        <div style="width: 40px; height: 40px; background: #ecfeff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #06b6d4;">
                            <i class="fa fa-clock-o"></i>
                        </div>
                    </div>
                </div>

            </div>

            <!-- INTERFACE TRAFFIC MONITOR -->
            <div class="mm-card" style="margin-bottom: 20px;">
                <div class="mm-section-header">
                    <div class="mm-section-icon" style="background: #eff6ff; color: #2563eb;"><i class="fa fa-code-fork"></i></div>
                    <h3>Interface Traffic Monitor</h3>
                </div>
                <div style="padding: 20px;">
                    <!-- Interface Selector -->
                    <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 20px;">
                        <select class="mm-select" id="interfaceSelect" onchange="selectInterface()" style="max-width: 360px;">
                            <option value="">Loading interfaces...</option>
                        </select>
                    </div>

                    <!-- Traffic Stats -->
                    <div id="interface-traffic-section">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                            <!-- Download RX -->
                            <div style="background: #f8fafc; border-radius: 10px; padding: 16px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                    <i class="fa fa-download" style="color: #2563eb;"></i>
                                    <span style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Download</span>
                                </div>
                                <div style="font-size: 18px; font-weight: 700; color: #2563eb; margin-bottom: 8px; white-space: nowrap;" id="rx-speed">—</div>
                                <div class="mm-progress-bar"><div class="mm-progress-fill" id="rx-progress" style="width: 0%; background: linear-gradient(90deg, #3b82f6, #2563eb);"></div></div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 6px;" id="rx-total">Total: —</div>
                            </div>
                            <!-- Upload TX -->
                            <div style="background: #f8fafc; border-radius: 10px; padding: 16px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                    <i class="fa fa-upload" style="color: #22c55e;"></i>
                                    <span style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Upload</span>
                                </div>
                                <div style="font-size: 18px; font-weight: 700; color: #22c55e; margin-bottom: 8px; white-space: nowrap;" id="tx-speed">—</div>
                                <div class="mm-progress-bar"><div class="mm-progress-fill" id="tx-progress" style="width: 0%; background: linear-gradient(90deg, #22c55e, #16a34a);"></div></div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 6px;" id="tx-total">Total: —</div>
                            </div>
                        </div>
                        <!-- Interface Details -->
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div style="background: #f8fafc; border-radius: 8px; padding: 10px 8px; text-align: center; overflow: hidden;">
                                <div style="font-size: 13px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="interface-packets-rx">—</div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">RX Packets</div>
                            </div>
                            <div style="background: #f8fafc; border-radius: 8px; padding: 10px 8px; text-align: center; overflow: hidden;">
                                <div style="font-size: 13px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="interface-packets-tx">—</div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">TX Packets</div>
                            </div>
                            <div style="background: #f8fafc; border-radius: 8px; padding: 10px 8px; text-align: center; overflow: hidden;">
                                <div style="font-size: 13px; font-weight: 700; color: #1e293b;" id="interface-link-speed">—</div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Link Speed</div>
                            </div>
                            <div style="background: #f8fafc; border-radius: 8px; padding: 10px 8px; text-align: center; overflow: hidden;">
                                <div style="font-size: 13px; font-weight: 700; color: #1e293b;" id="interface-mtu">—</div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">MTU</div>
                            </div>
                        </div>
                        <!-- Last Update -->
                        <div style="text-align: center; margin-top: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                            <span style="font-size: 11px; color: #94a3b8;"><i class="fa fa-clock-o"></i> Last update: <span id="interface-last-update">Waiting for interface sync...</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HOTSPOT LIVE TRAFFIC TABLE -->
            <div class="mm-card">
                <div class="mm-section-header" style="flex-wrap: wrap; gap: 10px;">
                    <div class="mm-section-icon" style="background: #fefce8; color: #ca8a04;"><i class="fa fa-wifi"></i></div>
                    <h3 id="userTableTitle">Hotspot Online Users</h3>
                    <!-- Search hanya tampil di desktop -->
                    <div class="mm-desktop-only" style="margin-left: auto; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: flex-end; min-width: 320px;">
                        <div style="position: relative; min-width: 280px; max-width: 360px; width: 100%;">
                            <i class="fa fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                            <input type="text" id="searchUsername" class="mm-input" placeholder="Search active hotspot users..." autocomplete="off" style="padding-left: 32px; padding-right: 28px; width: 100%;">
                            <button id="clearSearch" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 0; font-size: 14px;">✕</button>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px; padding: 6px 12px;">
                            <span style="font-size: 12px; color: #64748b;">Total <strong id="totalUsers" style="color: #1e293b; margin-left: 2px;">0</strong></span>
                            <span style="width: 4px; height: 4px; border-radius: 999px; background: #cbd5e1;"></span>
                            <span style="font-size: 12px; color: #64748b;">Filtered <strong id="filteredUsers" style="color: #2563eb; margin-left: 2px;">0</strong></span>
                        </div>
                    </div>
                    <!-- Total/Filtered hanya di mobile (search ada di bawah) -->
                    <div class="mm-mobile-only" style="width: 100%; display: flex; align-items: center; gap: 8px; padding-top: 4px;">
                        <span style="font-size: 12px; color: #64748b;">Total: <strong id="totalUsersMobile" style="color: #1e293b;">0</strong></span>
                        <span style="font-size: 12px; color: #64748b;">Filtered: <strong id="filteredUsersMobile" style="color: #2563eb;">0</strong></span>
                    </div>
                </div>
                <div style="padding: 14px 16px 0; border-bottom: 1px solid #f1f5f9; background: #fff;">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" id="serviceTabHotspot" class="mm-btn mm-btn-primary mm-btn-sm" onclick="switchUserService('hotspot')">
                            <i class="fa fa-wifi"></i> Hotspot Users
                        </button>
                        <button type="button" id="serviceTabPPPoE" class="mm-btn mm-btn-ghost mm-btn-sm" onclick="switchUserService('pppoe')">
                            <i class="fa fa-plug"></i> PPPoE Users
                        </button>
                    </div>
                    <div id="userServiceHint" style="font-size: 12px; color: #64748b; padding: 10px 0 14px;">Showing currently active Hotspot sessions.</div>
                </div>

                <!-- MOBILE SORT (hidden on desktop) -->
                <div class="mm-mobile-only" style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px;">
                    <button id="sortMobileUsername" class="mm-btn mm-btn-ghost mm-btn-sm mobile-sort-btn" onclick="sortMobileData('username')" style="justify-content: center; font-size: 12px;">
                        <i class="fa fa-user"></i> Username <span class="sort-indicator" id="username-indicator"></span>
                    </button>
                    <button id="sortMobileIP" class="mm-btn mm-btn-ghost mm-btn-sm mobile-sort-btn" onclick="sortMobileData('ip')" style="justify-content: center; font-size: 12px;">
                        <i class="fa fa-globe"></i> IP <span class="sort-indicator" id="ip-indicator"></span>
                    </button>
                    <button id="sortMobileUsage" class="mm-btn mm-btn-ghost mm-btn-sm mobile-sort-btn" onclick="sortMobileData('usage')" style="justify-content: center; font-size: 12px;">
                        <i class="fa fa-bar-chart"></i> Usage <span class="sort-indicator" id="usage-indicator"></span>
                    </button>
                </div>

                <!-- MOBILE SEARCH (hidden on desktop) -->
                <div class="mm-mobile-only" style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
                    <div style="position: relative;">
                        <i class="fa fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                        <input type="text" id="mobileSearchUsername" class="mm-input" placeholder="Search username..." autocomplete="off" style="padding-left: 32px; font-size: 14px;">
                        <button id="mobileClearSearch" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 16px;">✕</button>
                    </div>
                </div>

                <!-- MOBILE USER LIST -->
                <div class="mm-mobile-only">
                    <!-- Mobile Pagination Controls -->
                    <div style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-size: 12px; color: #64748b;">Show:</label>
                            <select id="mobileRowsPerPage" class="mm-select" style="width: auto;" onchange="changeMobileRowsPerPage()">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 12px; color: #64748b;" id="mobilePaginationInfo">1 of 1</span>
                            <button class="mm-pagination-btn" id="mobilePrevBtn" onclick="previousPage()"><i class="fa fa-chevron-left"></i></button>
                            <button class="mm-pagination-btn" id="mobileNextBtn" onclick="nextPage()"><i class="fa fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div id="mobileUserList" style="padding: 12px 16px;"></div>
                </div>

                <!-- DESKTOP TABLE -->
                <div class="mm-desktop-only">
                    <div style="overflow-x: auto;">
                        <table class="mm-table" style="min-width: 900px;">
                            <thead>
                                <tr>
                                    <th data-sort="username" onclick="sortTable('username')" class="sortable">Username</th>
                                    <th>Comment</th>
                                    <th data-sort="ip" onclick="sortTable('ip')" class="sortable">IP Address</th>
                                    <th>Uptime</th>
                                    <th data-sort="usage" onclick="sortTable('usage')" class="sortable">Total Usage</th>
                                    <th style="text-align:center;">Traffic</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pppoe-tbody">
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fa fa-spinner mm-spin"></i> Loading users...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div style="padding: 14px 20px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="font-size: 12px; color: #64748b;">Show:</label>
                            <select id="rowsPerPage" class="mm-select" style="width: auto;" onchange="changeRowsPerPage()">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="40">40</option>
                                <option value="50">50</option>
                                <option value="60">60</option>
                                <option value="80">80</option>
                                <option value="100">100</option>
                            </select>
                            <span style="font-size: 12px; color: #64748b;">entries</span>
                        </div>
                        <span style="font-size: 12px; color: #64748b;" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                        <div style="display: flex; gap: 4px;" id="paginationButtons">
                            <button class="mm-pagination-btn" id="firstBtn" onclick="goToPage(1)" disabled><i class="fa fa-angle-double-left"></i></button>
                            <button class="mm-pagination-btn" id="prevBtn" onclick="previousPage()" disabled><i class="fa fa-angle-left"></i></button>
                            <div id="pageNumbers" style="display: flex; gap: 4px;"></div>
                            <button class="mm-pagination-btn" id="nextBtn" onclick="nextPage()" disabled><i class="fa fa-angle-right"></i></button>
                            <button class="mm-pagination-btn" id="lastBtn" onclick="goToPage(0)" disabled><i class="fa fa-angle-double-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- END DASHBOARD TAB -->


    <!-- ===== HISTORY TAB ===== -->
    <div id="content-history" style="display: none;">

            <!-- Configuration Cards Row — 2 card gabungan -->

            <!-- Filter Card -->
            <div class="mm-card mm-history-filter-card" style="margin-bottom: 16px;">
                <div class="mm-section-header">
                    <div class="mm-section-icon" style="background: #f0fdf4; color: #16a34a;"><i class="fa fa-filter"></i></div>
                    <h3>Filter Usage History</h3>
                </div>
                <div class="mm-history-filter-shell">
                    <div class="mm-history-filter-grid" id="historyFilterGrid">
                        <div class="mm-history-field">
                            <label class="mm-label">Filter Type</label>
                            <select class="mm-select mm-history-control" id="historyFilterType" onchange="toggleHistoryFilter()">
                                <option value="month">Per Month</option>
                                <option value="range">Custom Range</option>
                            </select>
                        </div>
                        <div class="mm-history-field" id="historyYearWrap">
                            <label class="mm-label">Year</label>
                            <select class="mm-select mm-history-control" id="historyYear"></select>
                        </div>
                        <div class="mm-history-field" id="historyMonthWrap">
                            <label class="mm-label">Month</label>
                            <select class="mm-select mm-history-control" id="historyMonth">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div class="mm-history-field" id="historyDateFromWrap" style="display:none;">
                            <label class="mm-label">From</label>
                            <input type="date" class="mm-input mm-history-control" id="historyDateFrom">
                        </div>
                        <div class="mm-history-field" id="historyDateToWrap" style="display:none;">
                            <label class="mm-label">To</label>
                            <input type="date" class="mm-input mm-history-control" id="historyDateTo">
                        </div>
                    </div>

                    <div class="mm-history-filter-divider"></div>

                    <div class="mm-history-search-row">
                        <div class="mm-history-searchbox">
                            <label class="mm-label">Find User</label>
                            <div class="mm-history-search-input">
                                <i class="fa fa-search"></i>
                                <input type="text" class="mm-input" id="historySearch" placeholder="Search username or comment...">
                            </div>
                        </div>
                        <div class="mm-history-actions">
                            <button class="mm-btn mm-btn-primary" onclick="loadUsageHistory()">
                                <i class="fa fa-filter"></i> Apply
                            </button>
                            <button class="mm-btn mm-btn-ghost" onclick="clearHistoryFilter()">
                                <i class="fa fa-times"></i> Clear
                            </button>
                        </div>
                    </div>

                    <div class="mm-history-quickbar">
                        <span class="mm-history-quick-label">Quick Range</span>
                        <button class="mm-btn mm-btn-ghost mm-btn-sm mm-history-chip" onclick="setHistoryMonth(0)">This Month</button>
                        <button class="mm-btn mm-btn-ghost mm-btn-sm mm-history-chip" onclick="setHistoryMonth(-1)">Last Month</button>
                        <button class="mm-btn mm-btn-ghost mm-btn-sm mm-history-chip" onclick="setHistoryMonth(-2)">2 Months Ago</button>
                        <button class="mm-btn mm-btn-ghost mm-btn-sm mm-history-chip" onclick="setHistoryMonth(-3)">3 Months Ago</button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards — 2 kolom mobile, 4 kolom desktop -->
            <div class="mm-history-stat-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
                <div class="mm-stat-card" style="--accent-from:#16a34a;--accent-to:#15803d;">
                    <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Total Users</div>
                    <div style="font-size: 22px; font-weight: 700; color: #1e293b;" id="historyTotalUsers">—</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">In this period</div>
                </div>
                <div class="mm-stat-card" style="--accent-from:#2563eb;--accent-to:#1d4ed8;">
                    <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Total Upload</div>
                    <div style="font-size: 22px; font-weight: 700; color: #16a34a;" id="historyTotalUpload">—</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">All users</div>
                </div>
                <div class="mm-stat-card" style="--accent-from:#7c3aed;--accent-to:#6d28d9;">
                    <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Total Download</div>
                    <div style="font-size: 22px; font-weight: 700; color: #2563eb;" id="historyTotalDownload">—</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">All users</div>
                </div>
                <div class="mm-stat-card" style="--accent-from:#f59e0b;--accent-to:#d97706;">
                    <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Total Usage</div>
                    <div style="font-size: 22px; font-weight: 700; color: #1e293b;" id="historyTotalUsage">—</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Up + Down</div>
                </div>
            </div>

            <!-- Top 3 Usage -->
            <div class="mm-card" style="margin-bottom: 16px;">
                <div class="mm-section-header">
                    <div class="mm-section-icon" style="background: #fffbeb; color: #d97706;"><i class="fa fa-trophy"></i></div>
                    <h3>Top 3 Usage</h3>
                    <span style="margin-left: auto; font-size: 11px; color: #94a3b8;" id="top3PeriodLabel"></span>
                </div>
                <div style="padding: 12px 16px;">
                    <div id="top3Container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                        <div style="text-align:center;padding:20px;color:#94a3b8;grid-column:1/-1;">
                            <i class="fa fa-trophy" style="font-size:24px;display:block;margin-bottom:6px;"></i>
                            Apply filter to see top usage
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Table Card -->
            <div class="mm-card">
                <div class="mm-section-header">
                    <div class="mm-section-icon" style="background: #eff6ff; color: #2563eb;"><i class="fa fa-table"></i></div>
                    <h3>Usage Detail</h3>
                    <span style="margin-left: auto; font-size: 12px; color: #64748b;" id="historyResultCount"></span>
                </div>

                <!-- Per page + info -->
                <div style="padding: 10px 16px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-size: 12px; color: #64748b;">Show:</label>
                        <select class="mm-select" id="historyPerPage" onchange="changeHistoryPerPage()" style="width: auto; font-size: 12px; padding: 5px 8px;">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span style="font-size: 12px; color: #64748b;">entries</span>
                    </div>
                    <span style="font-size: 12px; color: #64748b;" id="historyPaginationInfo">Showing 0 to 0 of 0 entries</span>
                </div>

                <!-- Desktop Table -->
                <div class="mm-desktop-only" style="overflow-x: auto;">
                    <table class="mm-table" style="min-width: 500px;">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th class="sortable" onclick="sortHistoryTable('username')" id="hth-username" style="cursor:pointer;user-select:none;">Username <span id="hsort-username"></span></th>
                                <th>Name</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th class="sortable" onclick="sortHistoryTable('usage')" id="hth-usage" style="cursor:pointer;user-select:none;">Total Usage <span id="hsort-usage" style="color:#2563eb;">↓</span></th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    <i class="fa fa-history" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                    Select a period and click Apply
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="mm-mobile-only" id="historyMobileList" style="padding: 12px;">
                    <div style="text-align:center;padding:32px;color:#94a3b8;">
                        <i class="fa fa-history" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                        Select a period and click Apply
                    </div>
                </div>

                <!-- Pagination buttons -->
                <div style="padding: 12px 16px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 4px;" id="historyPaginationButtons">
                </div>
            </div>

        </div>
        <!-- END HISTORY TAB -->

       

    </div>
    <!-- END Main Content -->

 </div>
<!-- END Main Data Usage UI -->

{/if}

{literal}
<script>
    // ===================================================
    // TAB SYSTEM
    // ===================================================
    function switchTab(tab) {
        var dashboard = document.getElementById('content-dashboard');
        var history = document.getElementById('content-history');
        var setup = document.getElementById('content-setup');

        if (dashboard) dashboard.style.display = tab === 'dashboard' ? 'block' : 'none';
        if (history) history.style.display = tab === 'history' ? 'block' : 'none';
        if (setup) setup.style.display = tab === 'setup' ? 'block' : 'none';

        var dashboardTab = document.getElementById('tab-dashboard');
        var historyTab = document.getElementById('tab-history');
        var setupTab = document.getElementById('tab-setup');

        if (dashboardTab) dashboardTab.className = 'mm-tab-btn' + (tab === 'dashboard' ? ' active' : '');
        if (historyTab) historyTab.className = 'mm-tab-btn' + (tab === 'history' ? ' active' : '');
        if (setupTab) setupTab.className = 'mm-tab-btn' + (tab === 'setup' ? ' active' : '');

        sessionStorage.setItem('data_usage_active_tab', tab);

        if (tab === 'dashboard') {
            // Resolve Hotspot vs PPPoE from router counts first, then load the matching user list.
            updateUserCounts(function() { loadUserList(); });
            setTimeout(function() { updateSystemMonitoring(); }, 400);
            setTimeout(function() { loadInterfaces(); }, 900);
        } else if (tab === 'history') {
            initializeHistoryTab();
        }
    }

    function initializeTabs() {
        // Cek apakah ini refresh (performance.navigation.type === 1)
        // atau navigasi baru (type === 0)
        var isRefresh = (performance && performance.navigation && performance.navigation.type === 1)
            || (performance && performance.getEntriesByType
                && performance.getEntriesByType('navigation')[0]
                && performance.getEntriesByType('navigation')[0].type === 'reload');

        if (isRefresh) {
            // Ini refresh — restore tab terakhir
            var savedTab = sessionStorage.getItem('data_usage_active_tab');
            if (savedTab === 'history') {
                switchTab('history');
            } else if (savedTab === 'setup') {
                switchTab('setup');
            } else {
                switchTab('dashboard');
            }
        } else {
            // Ini navigasi baru — clear sessionStorage, mulai dari Usage History (database-safe)
            sessionStorage.removeItem('data_usage_active_tab');
            switchTab('history');
        }
    }

    // ===================================================
    // USAGE HISTORY
    // ===================================================
    var historyInitialized  = false;
    var historyCurrentPage  = 1;
    var historyTotalPages   = 1;
    var historyPerPage      = 10;
    var historySortColumn   = 'usage';
    var historySortDir      = 'desc';
    var historyAllData      = [];

    function initializeHistoryTab() {
        if (!historyInitialized) {
            // Populate year dropdown
            var yearSel = document.getElementById('historyYear');
            var currentYear = new Date().getFullYear();
            for (var y = currentYear; y >= currentYear - 3; y--) {
                var opt = document.createElement('option');
                opt.value = y;
                opt.textContent = y;
                yearSel.appendChild(opt);
            }

            // Cek apakah ada filter tersimpan dari refresh
            var isRefresh = (performance && performance.navigation && performance.navigation.type === 1)
                || (performance && performance.getEntriesByType
                    && performance.getEntriesByType('navigation')[0]
                    && performance.getEntriesByType('navigation')[0].type === 'reload');

            var savedFilter = isRefresh ? sessionStorage.getItem('data_usage_history_filter') : null;

            if (savedFilter) {
                // Restore filter dari sessionStorage
                try {
                    var f = JSON.parse(savedFilter);
                    document.getElementById('historyFilterType').value = f.filterType || 'month';
                    document.getElementById('historyYear').value        = f.year || currentYear;
                    document.getElementById('historyMonth').value       = f.month || (new Date().getMonth() + 1);
                    document.getElementById('historyDateFrom').value    = f.dateFrom || '';
                    document.getElementById('historyDateTo').value      = f.dateTo || '';
                    document.getElementById('historySearch').value      = f.search || '';
                    document.getElementById('historyPerPage').value     = f.perPage || 10;
                    historyPerPage = parseInt(f.perPage) || 10;
                    toggleHistoryFilter();
                } catch(e) {}
            } else {
                // Default: bulan ini
                var monthSel = document.getElementById('historyMonth');
                monthSel.value = new Date().getMonth() + 1;
                var today    = new Date();
                var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                document.getElementById('historyDateFrom').value = firstDay.toISOString().split('T')[0];
                document.getElementById('historyDateTo').value   = today.toISOString().split('T')[0];
            }

            historyInitialized = true;
        }
        loadUsageHistory();
    }

    function toggleHistoryFilter() {
        var type    = document.getElementById('historyFilterType').value;
        var isMonth = type === 'month';
        var grid = document.getElementById('historyFilterGrid');
        document.getElementById('historyYearWrap').style.display     = isMonth ? 'block' : 'none';
        document.getElementById('historyMonthWrap').style.display    = isMonth ? 'block' : 'none';
        document.getElementById('historyDateFromWrap').style.display = isMonth ? 'none'  : 'block';
        document.getElementById('historyDateToWrap').style.display   = isMonth ? 'none'  : 'block';
        if (grid) {
            if (isMonth) grid.classList.remove('range-mode');
            else grid.classList.add('range-mode');
        }
    }

    function sortHistoryTable(column) {
        if (historySortColumn === column) {
            historySortDir = historySortDir === 'asc' ? 'desc' : 'asc';
        } else {
            historySortColumn = column;
            historySortDir    = column === 'usage' ? 'desc' : 'asc';
        }

        // Sort historyAllData
        historyAllData.sort(function(a, b) {
            var cmp = 0;
            if (column === 'username') {
                var aVal = (a.username || '').toLowerCase();
                var bVal = (b.username || '').toLowerCase();
                cmp = aVal.localeCompare(bVal);
            } else if (column === 'usage') {
                cmp = (a.total_bytes || 0) - (b.total_bytes || 0);
            }
            return historySortDir === 'asc' ? cmp : -cmp;
        });

        // Update sort indicators
        document.getElementById('hsort-username').textContent = '';
        document.getElementById('hsort-usage').textContent    = '';
        var arrow = historySortDir === 'asc' ? ' ↑' : ' ↓';
        if (column === 'username') document.getElementById('hsort-username').textContent = arrow;
        if (column === 'usage')    document.getElementById('hsort-usage').textContent    = arrow;

        // Re-render halaman pertama dengan data yang sudah disort
        historyCurrentPage = 1;
        renderHistoryFromCache();
    }

    function renderHistoryFromCache() {
        var offset  = (historyCurrentPage - 1) * historyPerPage;
        var paged   = historyAllData.slice(offset, offset + historyPerPage);
        renderHistoryTable(paged, historyAllData.length, historyCurrentPage, historyPerPage);
    }

    function clearHistoryFilter() {
        // Reset semua filter ke default
        var now = new Date();
        document.getElementById('historyFilterType').value = 'month';
        document.getElementById('historyYear').value       = now.getFullYear();
        document.getElementById('historyMonth').value      = now.getMonth() + 1;
        document.getElementById('historySearch').value     = '';
        var firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        document.getElementById('historyDateFrom').value   = firstDay.toISOString().split('T')[0];
        document.getElementById('historyDateTo').value     = now.toISOString().split('T')[0];
        toggleHistoryFilter();
        loadUsageHistory();
    }

    function setHistoryMonth(offset) {
        var d = new Date();
        d.setMonth(d.getMonth() + offset);
        document.getElementById('historyFilterType').value = 'month';
        document.getElementById('historyYear').value  = d.getFullYear();
        document.getElementById('historyMonth').value = d.getMonth() + 1;
        toggleHistoryFilter();
        loadUsageHistory();
    }

    function loadUsageHistory(page) {
        if (!page) { page = 1; historyCurrentPage = 1; }
        var filterType = document.getElementById('historyFilterType').value;
        var search     = document.getElementById('historySearch').value.trim();
        historyPerPage = parseInt(document.getElementById('historyPerPage').value) || 10;

        var url = buildApiUrl('user_monitor_ui') + '&action=get_usage_history&filter_type=' + filterType +
                  '&page=' + page + '&per_page=' + historyPerPage;

        if (filterType === 'month') {
            var year  = document.getElementById('historyYear').value;
            var month = document.getElementById('historyMonth').value;
            url += '&year=' + year + '&month=' + month;
        } else {
            var dateFrom = document.getElementById('historyDateFrom').value;
            var dateTo   = document.getElementById('historyDateTo').value;
            if (!dateFrom || !dateTo) {
                showNotification('warning', 'Date Required', 'Please select date range');
                return;
            }
            url += '&date_from=' + encodeURIComponent(dateFrom) + '&date_to=' + encodeURIComponent(dateTo);
        }

        if (search) url += '&search=' + encodeURIComponent(search);

        // Simpan filter ke sessionStorage saat Apply
        sessionStorage.setItem('data_usage_history_filter', JSON.stringify({
            filterType : filterType,
            year       : document.getElementById('historyYear').value,
            month      : document.getElementById('historyMonth').value,
            dateFrom   : document.getElementById('historyDateFrom').value,
            dateTo     : document.getElementById('historyDateTo').value,
            search     : search,
            perPage    : historyPerPage
        }));

        var tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8;"><i class="fa fa-spinner mm-spin"></i> Loading...</td></tr>';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.timeout = 10000;
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                // Detect if we got redirected (HTML instead of JSON = redirect to login)
                if (xhr.responseText && (xhr.responseText.startsWith('<!DOCTYPE') || xhr.responseText.startsWith('<html'))) {
                    pauseRealtimeActivity();
                    return;
                }
                if (xhr.status === 200) {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            historyCurrentPage = r.page;
                            historyTotalPages  = r.total_pages;
                            historyAllData     = r.data;
                            // Terapkan sort yang aktif
                            if (historySortColumn) {
                                historyAllData.sort(function(a, b) {
                                    var cmp = 0;
                                    if (historySortColumn === 'username') {
                                        cmp = (a.username||'').toLowerCase().localeCompare((b.username||'').toLowerCase());
                                    } else if (historySortColumn === 'usage') {
                                        cmp = (a.total_bytes||0) - (b.total_bytes||0);
                                    }
                                    return historySortDir === 'asc' ? cmp : -cmp;
                                });
                            }
                            renderHistoryTable(r.data, r.total_count, r.page, r.per_page);
                        } else {
                            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#ef4444;">' + (r.error || 'Failed to load') + '</td></tr>';
                        }
                    } catch(e) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#ef4444;">Invalid response</td></tr>';
                    }
                }
            }
        };
        xhr.ontimeout = function() {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#f59e0b;">Request timeout</td></tr>';
        };
        xhr.send();
    }

    function changeHistoryPerPage() {
        historyCurrentPage = 1;
        loadUsageHistory(1);
    }

    function historyGoToPage(page) {
        if (page < 1 || page > historyTotalPages) return;
        historyCurrentPage = page;
        loadUsageHistory(page);
    }

    function renderHistoryTable(data, totalCount, page, perPage) {
        var tbody = document.getElementById('historyTableBody');

        // Pastikan nilai tidak NaN
        page     = parseInt(page)    || 1;
        perPage  = parseInt(perPage) || 10;
        totalCount = parseInt(totalCount) || 0;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#94a3b8;"><i class="fa fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>No data found for this period</td></tr>';
            document.getElementById('historyTotalUsers').textContent     = '0';
            document.getElementById('historyTotalUpload').textContent    = '—';
            document.getElementById('historyTotalDownload').textContent  = '—';
            document.getElementById('historyTotalUsage').textContent     = '—';
            document.getElementById('historyResultCount').textContent    = '0 users';
            document.getElementById('historyPaginationInfo').textContent = 'Showing 0 to 0 of 0 entries';
            document.getElementById('historyPaginationButtons').innerHTML = '';
            return;
        }

        // Render desktop table rows + mobile cards
        var startNum   = ((page - 1) * perPage) + 1;
        var html       = '';
        var mobileHtml = '';
        data.forEach(function(row, index) {
            var num = startNum + index;
            // Desktop row
            html += '<tr>';
            html += '<td style="color:#94a3b8;font-size:12px;">' + num + '</td>';
            html += '<td style="font-weight:600;color:#1e293b;font-family:monospace;font-size:13px;">' + (row.username || '-') + '</td>';
            html += '<td style="color:#475569;font-size:13px;">' + (row.user_comment || '-') + '</td>';
            html += '<td class="mm-upload" style="font-family:monospace;font-size:13px;">' + (row.total_upload || '-') + '</td>';
            html += '<td class="mm-download" style="font-family:monospace;font-size:13px;">' + (row.total_download || '-') + '</td>';
            html += '<td class="mm-usage" style="font-family:monospace;font-weight:700;font-size:13px;">' + (row.total_usage || '-') + '</td>';
            html += '</tr>';
            // Mobile card
            mobileHtml += '<div class="mm-mobile-card">';
            mobileHtml += '<div class="mm-mobile-card-header">';
            mobileHtml += '<span style="font-weight:700;color:#1e293b;font-family:monospace;font-size:13px;"><i class="fa fa-user" style="font-size:11px;margin-right:4px;color:#64748b;"></i>' + (row.username || '-') + '</span>';
            mobileHtml += '<span class="mm-badge mm-badge-gray" style="font-size:11px;">#' + num + '</span>';
            mobileHtml += '</div>';
            mobileHtml += '<div class="mm-mobile-card-body">';
            mobileHtml += '<div class="mm-mobile-row"><span class="mm-mobile-label">Name</span><span class="mm-mobile-value" style="font-size:12px;">' + (row.user_comment || '-') + '</span></div>';
            mobileHtml += '<div class="mm-mobile-row"><span class="mm-mobile-label">Upload</span><span class="mm-mobile-value mm-upload" style="font-family:monospace;">' + (row.total_upload || '-') + '</span></div>';
            mobileHtml += '<div class="mm-mobile-row"><span class="mm-mobile-label">Download</span><span class="mm-mobile-value mm-download" style="font-family:monospace;">' + (row.total_download || '-') + '</span></div>';
            mobileHtml += '<div class="mm-mobile-row"><span class="mm-mobile-label">Total Usage</span><span class="mm-mobile-value mm-usage" style="font-family:monospace;font-weight:700;">' + (row.total_usage || '-') + '</span></div>';
            mobileHtml += '</div></div>';
        });
        tbody.innerHTML = html;
        var mobileList = document.getElementById('historyMobileList');
        if (mobileList) mobileList.innerHTML = mobileHtml || '<div style="text-align:center;padding:32px;color:#94a3b8;">No data</div>';

        // Update pagination info
        var endNum = Math.min(page * perPage, totalCount);
        document.getElementById('historyPaginationInfo').textContent =
            'Showing ' + startNum + ' to ' + endNum + ' of ' + totalCount + ' entries';

        // Render pagination buttons
        var btnHtml = '';
        btnHtml += '<button class="mm-pagination-btn" onclick="historyGoToPage(1)" ' + (page <= 1 ? 'disabled' : '') + '><i class="fa fa-angle-double-left"></i></button>';
        btnHtml += '<button class="mm-pagination-btn" onclick="historyGoToPage(' + (page - 1) + ')" ' + (page <= 1 ? 'disabled' : '') + '><i class="fa fa-angle-left"></i></button>';

        var startPage = Math.max(1, page - 2);
        var endPage   = Math.min(historyTotalPages, page + 2);
        for (var p = startPage; p <= endPage; p++) {
            btnHtml += '<button class="mm-pagination-btn' + (p === page ? ' active' : '') + '" onclick="historyGoToPage(' + p + ')">' + p + '</button>';
        }

        btnHtml += '<button class="mm-pagination-btn" onclick="historyGoToPage(' + (page + 1) + ')" ' + (page >= historyTotalPages ? 'disabled' : '') + '><i class="fa fa-angle-right"></i></button>';
        btnHtml += '<button class="mm-pagination-btn" onclick="historyGoToPage(' + historyTotalPages + ')" ' + (page >= historyTotalPages ? 'disabled' : '') + '><i class="fa fa-angle-double-right"></i></button>';

        document.getElementById('historyPaginationButtons').innerHTML = btnHtml;

        // Update summary
        var upSum   = 0;
        var downSum = 0;
        var totalAll = 0;
        data.forEach(function(row) {
            upSum    += parseHistoryBytes(row.total_upload);
            downSum  += parseHistoryBytes(row.total_download);
            totalAll += (row.total_bytes || 0);
        });
        document.getElementById('historyTotalUpload').textContent    = formatHistoryBytes(upSum);
        document.getElementById('historyTotalDownload').textContent  = formatHistoryBytes(downSum);
        document.getElementById('historyTotalUsage').textContent     = formatHistoryBytes(totalAll);
        document.getElementById('historyTotalUsers').textContent     = totalCount;
        document.getElementById('historyResultCount').textContent    = totalCount + ' users';

        // Render Top 3 dari historyAllData (sudah sorted by total_bytes desc)
        renderTop3();
    }

    function renderTop3() {
        var container = document.getElementById('top3Container');
        var label     = document.getElementById('top3PeriodLabel');
        if (!container) return;

        // Update label periode
        var filterType = document.getElementById('historyFilterType').value;
        if (filterType === 'month') {
            var monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
            var m = parseInt(document.getElementById('historyMonth').value);
            var y = document.getElementById('historyYear').value;
            if (label) label.textContent = monthNames[m] + ' ' + y;
        } else {
            var from = document.getElementById('historyDateFrom').value;
            var to   = document.getElementById('historyDateTo').value;
            if (label) label.textContent = from + ' — ' + to;
        }

        // Ambil top 3 dari historyAllData
        var top3 = historyAllData.slice(0, 3);

        if (top3.length === 0) {
            container.innerHTML = '<div style="text-align:center;padding:20px;color:#94a3b8;grid-column:1/-1;"><i class="fa fa-trophy" style="font-size:24px;display:block;margin-bottom:6px;"></i>No data</div>';
            return;
        }

        var medals  = ['🥇', '🥈', '🥉'];
        var colors  = ['#d97706', '#2563eb', '#dc2626'];
        var bgColors = ['#fffbeb', '#eff6ff', '#fef2f2'];
        var html    = '';

        top3.forEach(function(row, i) {
            html += '<div style="background:' + bgColors[i] + ';border-radius:12px;padding:14px 16px;text-align:center;border:1px solid #e2e8f0;">';
            html += '<div style="font-size:24px;margin-bottom:6px;">' + medals[i] + '</div>';
            html += '<div style="font-size:13px;font-weight:700;color:#1e293b;font-family:monospace;margin-bottom:2px;">' + (row.username || '-') + '</div>';
            html += '<div style="font-size:11px;color:#64748b;margin-bottom:8px;">' + (row.user_comment || '-') + '</div>';
            html += '<div style="font-size:16px;font-weight:700;color:' + colors[i] + ';">' + (row.total_usage || '0 B') + '</div>';
            html += '<div style="font-size:10px;color:#94a3b8;margin-top:4px;">';
            html += '<span style="color:#16a34a;">↑ ' + (row.total_upload || '0 B') + '</span>';
            html += ' &nbsp; ';
            html += '<span style="color:#2563eb;">↓ ' + (row.total_download || '0 B') + '</span>';
            html += '</div>';
            html += '</div>';
        });

        container.innerHTML = html;
    }

    function parseHistoryBytes(str) {
        if (!str || str === '-') return 0;
        var clean = str.replace(/,/g, '').trim();
        var parts = clean.match(/([0-9.]+)\s*([KMGT]?B)/i);
        if (!parts) return 0;
        var val  = parseFloat(parts[1]);
        var unit = parts[2].toUpperCase();
        var multiplier = { 'B':1, 'KB':1024, 'MB':1024*1024, 'GB':1024*1024*1024, 'TB':1024*1024*1024*1024 };
        return isNaN(val) ? 0 : val * (multiplier[unit] || 1);
    }

    function formatHistoryBytes(bytes) {
        var units = ['B','KB','MB','GB','TB'];
        var val   = Math.max(bytes || 0, 0);
        var pow   = Math.floor(val ? Math.log(val) / Math.log(1024) : 0);
        pow = Math.min(pow, units.length - 1);
        val = val / Math.pow(1024, pow);
        return isNaN(val) ? '0 B' : val.toFixed(2) + ' ' + units[pow];
    }

    function createClickableIP(ipAddress) {
        if (!ipAddress || ipAddress === '-' || ipAddress === '') return '-';
        return '<span style="font-family:monospace;font-size:12px;color:#334155;">' + ipAddress + '</span>';
    }

    // ===================================================
    // PERFORMANCE CONFIG
    // ===================================================
    var PerformanceConfig = {
        getUpdateInterval: function(userCount) {
            if (userCount > 500) return 60000;
            if (userCount > 200) return 30000;
            if (userCount > 100) return 15000;
            return 10000;
        },
        getSystemInterval: function(userCount) {
            if (userCount > 500) return 60000;
            if (userCount > 200) return 45000;
            return 30000;
        },
        getTimeoutInterval: function(userCount) {
            if (userCount > 500) return 30000;
            if (userCount > 200) return 20000;
            if (userCount > 100) return 10000;
            return 15000;
        },
        batchSize: 50,
        batchDelay: 10,
        cacheTimeout: 10000,
        enableCache: true,
        /** MikroTik API calls can exceed 10s on busy routers or slow links */
        mikrotikXhrTimeoutMs: 35000
    };

    function getMikrotikXhrTimeout() {
        var t = PerformanceConfig.mikrotikXhrTimeoutMs;
        return (typeof t === 'number' && t > 0) ? t : 35000;
    }

    // ===================================================
    // CLIENT CACHE
    // ===================================================
    var ClientCache = {
        cache: {},
        get: function(key) {
            var item = this.cache[key];
            if (!item) return null;
            if (Date.now() - item.timestamp > PerformanceConfig.cacheTimeout) {
                delete this.cache[key];
                return null;
            }
            return item.data;
        },
        set: function(key, data) { this.cache[key] = { data: data, timestamp: Date.now() }; },
        clear: function() { this.cache = {}; }
    };

    var currentRouterId = null;
    var sortColumn = null;
    var sortDirection = 'asc';
    var allUsers = [];
    var filteredUsers = [];
    var lastUserCount = 0;
    var currentPage = 1;
    var rowsPerPage = 10;
    var totalPages = 1;
    var displayedUsers = [];
    var mobileSortColumn = 'usage';
    var mobileSortDirection = 'desc';
    var currentUserService = 'hotspot';
    var dashboardTimers = { userList: null, system: null, counts: null, cleanup: null, startUserList: null, startMonitoring: null, startInterfaces: null };
    var requestState = { userList: false, system: false, counts: false };
    var realtimeState = { pageActive: true, pageVisible: !document.hidden, started: false, activeXhrs: [] };

    function canRunRealtime() {
        return realtimeState.pageActive && realtimeState.pageVisible;
    }

    function trackXhr(xhr) {
        if (!xhr) return xhr;
        realtimeState.activeXhrs.push(xhr);
        xhr.addEventListener('loadend', function() {
            var index = realtimeState.activeXhrs.indexOf(xhr);
            if (index !== -1) realtimeState.activeXhrs.splice(index, 1);
        });
        return xhr;
    }

    function abortActiveXhrs() {
        realtimeState.activeXhrs.slice().forEach(function(xhr) {
            try { xhr.abort(); } catch (e) {}
        });
        realtimeState.activeXhrs = [];
        requestState.system = false;
        requestState.counts = false;
        requestState.userList = false;
    }

    function clearDashboardTimers() {
        Object.keys(dashboardTimers).forEach(function(key) {
            if (dashboardTimers[key]) {
                clearTimeout(dashboardTimers[key]);
                clearInterval(dashboardTimers[key]);
                dashboardTimers[key] = null;
            }
        });
    }

    function pauseRealtimeActivity() {
        clearDashboardTimers();
        if (interfaceData && interfaceData.trafficInterval) {
            clearInterval(interfaceData.trafficInterval);
            interfaceData.trafficInterval = null;
        }
        if (_tmInterval) {
            clearInterval(_tmInterval);
            _tmInterval = null;
        }
        abortActiveXhrs();
    }

    function resumeRealtimeActivity() {
        if (!realtimeState.pageActive || !realtimeState.pageVisible || !realtimeState.started) return;
        autoCleanup();
        initializeMonitoring();
        loadUserList();
        if (interfaceData && !interfaceData.interfaces.length) loadInterfaces();
        if (interfaceData && interfaceData.selectedInterface) startInterfaceMonitoring();
        if (_tmUsername && !_tmInterval) {
            fetchUserTraffic();
            _tmInterval = setInterval(fetchUserTraffic, 2000);
        }
    }

    function setDashboardLastUpdated(text) {
        var el = document.getElementById('dashboard-last-update');
        if (el) el.textContent = text;
    }

    function setInterfaceLastUpdated(text) {
        var el = document.getElementById('interface-last-update');
        if (el) el.textContent = text;
    }

    function refreshServiceTabUi() {
        var hotspotBtn = document.getElementById('serviceTabHotspot');
        var pppoeBtn = document.getElementById('serviceTabPPPoE');
        var title = document.getElementById('userTableTitle');
        var hint = document.getElementById('userServiceHint');
        var search = document.getElementById('searchUsername');
        var mobileSearch = document.getElementById('mobileSearchUsername');

        if (hotspotBtn) hotspotBtn.className = 'mm-btn ' + (currentUserService === 'hotspot' ? 'mm-btn-primary' : 'mm-btn-ghost') + ' mm-btn-sm';
        if (pppoeBtn) pppoeBtn.className = 'mm-btn ' + (currentUserService === 'pppoe' ? 'mm-btn-primary' : 'mm-btn-ghost') + ' mm-btn-sm';
        if (title) title.textContent = currentUserService === 'hotspot' ? 'Hotspot Online Users' : 'PPPoE Online Users';
        if (hint) hint.textContent = currentUserService === 'hotspot' ? 'Showing currently active Hotspot sessions.' : 'Showing currently active PPPoE sessions.';
        if (search) search.placeholder = currentUserService === 'hotspot' ? 'Search active hotspot users...' : 'Search active PPPoE users...';
        if (mobileSearch) mobileSearch.placeholder = currentUserService === 'hotspot' ? 'Search hotspot user...' : 'Search PPPoE user...';
    }

    function restoreUserServicePreference() {
        try {
            var s = sessionStorage.getItem('data_usage_user_service');
            if (s === 'pppoe' || s === 'hotspot') {
                currentUserService = s;
            }
        } catch (e) {}
    }

    /** If the user has not chosen a tab yet, pick the service that actually has online sessions. */
    function maybeAutoSelectUserServiceFromRouterCounts(data) {
        if (!data || !data.success) return;
        try {
            if (sessionStorage.getItem('data_usage_user_service')) return;
        } catch (e) { return; }
        var th = parseInt(data.total_hotspot, 10) || 0;
        var tp = parseInt(data.total_pppoe, 10) || 0;
        if (th === 0 && tp > 0) {
            currentUserService = 'pppoe';
            refreshServiceTabUi();
        } else if (tp === 0 && th > 0) {
            currentUserService = 'hotspot';
            refreshServiceTabUi();
        }
    }

    function switchUserService(service) {
        if (service !== 'hotspot' && service !== 'pppoe') return;
        if (currentUserService === service) return;
        currentUserService = service;
        try { sessionStorage.setItem('data_usage_user_service', service); } catch (e) {}
        ClientCache.clear();
        refreshServiceTabUi();
        loadUserList();
    }

    function scheduleSystemPoll(delay) {
        if (!canRunRealtime()) return;
        if (dashboardTimers.system) clearTimeout(dashboardTimers.system);
        dashboardTimers.system = setTimeout(updateSystemMonitoring, delay);
    }

    function scheduleCountsPoll(delay) {
        if (!canRunRealtime()) return;
        if (dashboardTimers.counts) clearTimeout(dashboardTimers.counts);
        dashboardTimers.counts = setTimeout(updateUserCounts, delay);
    }

    // ===================================================
    // URL BUILDER
    // ===================================================
    function buildApiUrl(endpoint) {
        var currentUrl = window.location.href;
        currentUrl = currentUrl.split('#')[0];
        var baseUrl;
        if (currentUrl.includes('?_route=')) {
            var routeMatch = currentUrl.match(/\?_route=([^&]+)/);
            if (routeMatch) {
                var routePath = routeMatch[1];
                if (endpoint === 'user_monitor_ui') {
                    baseUrl = window.location.origin + window.location.pathname + '?_route=' + routePath;
                } else {
                    var newRoutePath = routePath.replace(/user_monitor_ui/, endpoint);
                    baseUrl = window.location.origin + window.location.pathname + '?_route=' + newRoutePath;
                }
            } else {
                baseUrl = window.location.origin + window.location.pathname + '?_route=' + endpoint;
            }
        } else {
            var currentPath = window.location.pathname;
            var pathParts = currentPath.split('/');
            var routerId = pathParts[pathParts.length - 1];
            if (routerId && routerId.match(/^\d+$/)) {
                pathParts[pathParts.length - 2] = endpoint;
            } else {
                pathParts[pathParts.length - 1] = endpoint;
            }
            baseUrl = window.location.origin + pathParts.join('/');
        }
        return baseUrl;
    }

    // ===================================================
    // UTILITY FUNCTIONS
    // ===================================================
    function usageToBytes(usageStr) {
        if (!usageStr || usageStr === '-' || usageStr === '' || usageStr === 'N/A') return 0;
        if (usageStr.trim() === '0 B' || usageStr.trim() === '0') return 0;
        var cleanStr = usageStr.replace(/,/g, '').trim();
        var parts = cleanStr.match(/([0-9.]+)\s*([KMGT]?B)/i);
        if (!parts) return 0;
        var value = parseFloat(parts[1]);
        var unit = parts[2].toUpperCase();
        if (value === 0 || isNaN(value)) return 0;
        var multiplier = { 'B': 1, 'KB': 1024, 'MB': 1024*1024, 'GB': 1024*1024*1024, 'TB': 1024*1024*1024*1024 };
        return value * (multiplier[unit] || 1);
    }

    function ipToNumber(ip) {
        if (!ip || ip === '-') return 0;
        var parts = ip.split('.');
        if (parts.length !== 4) return 0;
        return (parseInt(parts[0]) << 24) + (parseInt(parts[1]) << 16) + (parseInt(parts[2]) << 8) + parseInt(parts[3]);
    }

    function getTrafficIndicator(speed, maxLimit) {
        if (!speed || speed === '-' || speed === '0 B/s') return '<span class="mm-dot-none">●</span>';

        var bytes = speedToBytes(speed);
        if (bytes === 0) return '<span class="mm-dot-none">●</span>';

        // Jika ada max_limit, gunakan persentase
        if (maxLimit && maxLimit !== 'N/A' && maxLimit !== '') {
            var limitBytes = parseLimitBytes(maxLimit);
            if (limitBytes > 0) {
                var pct = (bytes / limitBytes) * 100;
                if (pct >= 80) return '<span class="mm-dot-high">●</span>';
                if (pct >= 50) return '<span class="mm-dot-medium">●</span>';
                if (pct >= 20) return '<span class="mm-dot-low">●</span>';
                return '<span class="mm-dot-none" style="color:#3b82f6;">●</span>';
            }
        }

        // Fallback hardcode jika tidak ada limit
        var value = parseFloat(speed);
        if (speed.includes('MB') && value >= 5)  return '<span class="mm-dot-high">●</span>';
        if (speed.includes('MB') && value >= 1)  return '<span class="mm-dot-medium">●</span>';
        if (speed.includes('KB') && value >= 500)return '<span class="mm-dot-medium">●</span>';
        if (value > 0) return '<span class="mm-dot-low">●</span>';
        return '<span class="mm-dot-none">●</span>';
    }

    function speedToBytes(speed) {
        if (!speed || speed === '-' || speed === '0 B/s') return 0;
        var val = parseFloat(speed);
        if (isNaN(val) || val === 0) return 0;
        if (speed.includes('GB/s')) return val * 1024 * 1024 * 1024;
        if (speed.includes('MB/s')) return val * 1024 * 1024;
        if (speed.includes('KB/s')) return val * 1024;
        return val;
    }

    function parseLimitBytes(limit) {
        // Format MikroTik: "10M/10M" atau "10240k/10240k" atau "10M" dll
        if (!limit || limit === 'N/A' || limit === '') return 0;
        // Ambil bagian download (setelah /)
        var parts = limit.split('/');
        var dl = parts.length > 1 ? parts[1] : parts[0];
        dl = dl.trim().toUpperCase();
        var val = parseFloat(dl);
        if (isNaN(val) || val === 0) return 0;
        if (dl.includes('G')) return val * 1024 * 1024 * 1024;
        if (dl.includes('M')) return val * 1024 * 1024;
        if (dl.includes('K')) return val * 1024;
        return val;
    }

    function getSpeedColor(speed, maxLimit) {
        if (!speed || speed === '-' || speed === '0 B/s') return '#94a3b8';
        var bytes = speedToBytes(speed);
        if (bytes === 0) return '#94a3b8';

        if (maxLimit && maxLimit !== 'N/A' && maxLimit !== '') {
            var limitBytes = parseLimitBytes(maxLimit);
            if (limitBytes > 0) {
                var pct = (bytes / limitBytes) * 100;
                if (pct >= 80) return '#ef4444'; // merah
                if (pct >= 50) return '#f59e0b'; // kuning
                if (pct >= 20) return '#22c55e'; // hijau
                return '#3b82f6';                // biru
            }
        }

        // Fallback hardcode
        var value = parseFloat(speed);
        if (speed.includes('MB') && value >= 5)  return '#ef4444';
        if (speed.includes('MB') && value >= 1)  return '#f59e0b';
        if (speed.includes('KB') && value >= 500)return '#f59e0b';
        if (value > 0) return '#22c55e';
        return '#94a3b8';
    }

    // ===================================================
    // ACTIVITY BADGE
    // ===================================================
    function getActivityBadge(upload, download, maxLimit) {
        upload   = upload   || '0 B/s';
        download = download || '0 B/s';

        var upBytes   = speedToBytes(upload);
        var downBytes = speedToBytes(download);
        var maxBytes  = Math.max(upBytes, downBytes);

        if (maxBytes === 0) {
            return '<span style="display:inline-flex;align-items:center;gap:5px;">' +
                '<span style="width:9px;height:9px;border-radius:50%;background:#e2e8f0;display:inline-block;flex-shrink:0;"></span>' +
                '<span style="font-size:12px;color:#94a3b8;">Idle</span></span>';
        }

        var color, label, glow;

        // Dynamic berdasarkan persentase limit
        if (maxLimit && maxLimit !== 'N/A' && maxLimit !== '') {
            var limitBytes = parseLimitBytes(maxLimit);
            if (limitBytes > 0) {
                var pct = (maxBytes / limitBytes) * 100;
                if (pct >= 80) {
                    color = '#ef4444'; label = 'High';   glow = 'rgba(239,68,68,0.6)';
                } else if (pct >= 50) {
                    color = '#f59e0b'; label = 'Medium'; glow = 'rgba(245,158,11,0.6)';
                } else if (pct >= 20) {
                    color = '#22c55e'; label = 'Low';    glow = 'rgba(34,197,94,0.6)';
                } else {
                    color = '#3b82f6'; label = 'Active'; glow = 'rgba(59,130,246,0.6)';
                }
                return '<span style="display:inline-flex;align-items:center;gap:5px;">' +
                    '<span style="width:9px;height:9px;border-radius:50%;background:' + color + ';display:inline-block;flex-shrink:0;box-shadow:0 0 5px ' + glow + ';"></span>' +
                    '<span style="font-size:12px;color:' + color + ';font-weight:600;">' + label + '</span></span>';
            }
        }

        // Fallback hardcode
        var mbps = maxBytes / (1024 * 1024);
        if (mbps >= 5)   { color = '#ef4444'; label = 'High';   glow = 'rgba(239,68,68,0.6)'; }
        else if (mbps >= 1)   { color = '#f59e0b'; label = 'Medium'; glow = 'rgba(245,158,11,0.6)'; }
        else if (mbps >= 0.1) { color = '#22c55e'; label = 'Low';    glow = 'rgba(34,197,94,0.6)'; }
        else                  { color = '#3b82f6'; label = 'Active'; glow = 'rgba(59,130,246,0.6)'; }

        return '<span style="display:inline-flex;align-items:center;gap:5px;">' +
            '<span style="width:9px;height:9px;border-radius:50%;background:' + color + ';display:inline-block;flex-shrink:0;box-shadow:0 0 5px ' + glow + ';"></span>' +
            '<span style="font-size:12px;color:' + color + ';font-weight:600;">' + label + '</span></span>';
    }

    // ===================================================
    // SORT
    // ===================================================
    function sortTable(column) {
        if (sortColumn === column) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortDirection = 'asc';
            sortColumn = column;
        }
        filteredUsers.sort(function(a, b) {
            var aVal, bVal, cmp = 0;
            switch (column) {
                case 'username': aVal = a.username.toLowerCase(); bVal = b.username.toLowerCase(); cmp = aVal.localeCompare(bVal); break;
                case 'ip':       aVal = ipToNumber(a.address||'0.0.0.0'); bVal = ipToNumber(b.address||'0.0.0.0'); cmp = aVal - bVal; break;
                case 'usage':    aVal = usageToBytes(a.total_usage||'0 B'); bVal = usageToBytes(b.total_usage||'0 B'); cmp = aVal - bVal; break;
                default: return 0;
            }
            return sortDirection === 'asc' ? cmp : -cmp;
        });
        currentPage = 1;
        calculatePagination();
        displayUsers(displayedUsers);
        updateSortIndicators(column, sortDirection);
    }

    function updateSortIndicators(activeColumn, direction) {
        document.querySelectorAll('th[data-sort]').forEach(function(h) {
            h.classList.remove('mm-sort-asc', 'mm-sort-desc');
        });
        var activeHeader = document.querySelector('th[data-sort="' + activeColumn + '"]');
        if (activeHeader) activeHeader.classList.add(direction === 'asc' ? 'mm-sort-asc' : 'mm-sort-desc');
    }

    // ===================================================
    // USER LIST
    // ===================================================
    function loadUserList() {
        if (!canRunRealtime()) return;
        if (requestState.userList) return;

        var cacheKey = 'user_list_data_' + currentUserService;
        var cached = ClientCache.get(cacheKey);
        if (cached && PerformanceConfig.enableCache && allUsers.length === 0) {
            processUserListData(cached);
        }

        requestState.userList = true;
        var xhr = trackXhr(new XMLHttpRequest());
        var url = buildApiUrl('user_monitor_ui') + '&action=get_pppoe_users&service=' + encodeURIComponent(currentUserService);
        xhr.open('GET', url, true);
        xhr.timeout = getMikrotikXhrTimeout();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                requestState.userList = false;
                // Detect if redirected (HTML response = redirect to login)
                if (xhr.responseText && (xhr.responseText.startsWith('<!DOCTYPE') || xhr.responseText.startsWith('<html'))) {
                    pauseRealtimeActivity();
                    return;
                }
                if (xhr.status === 200) {
                    try {
                        var users = JSON.parse(xhr.responseText);
                        if (users && users.error) {
                            var tbodyErr = document.getElementById('pppoe-tbody');
                            if (tbodyErr && allUsers.length === 0) {
                                tbodyErr.innerHTML = '';
                                var trErr = document.createElement('tr');
                                var tdErr = document.createElement('td');
                                tdErr.setAttribute('colspan', '10');
                                tdErr.style.textAlign = 'center';
                                tdErr.style.padding = '40px';
                                tdErr.style.color = '#ef4444';
                                tdErr.textContent = String(users.error);
                                trErr.appendChild(tdErr);
                                tbodyErr.appendChild(trErr);
                            }
                            setDashboardLastUpdated('User list error');
                            return;
                        }
                        if (!Array.isArray(users)) {
                            setDashboardLastUpdated('User list: invalid response');
                            return;
                        }
                        ClientCache.set(cacheKey, users);
                        processUserListData(users);
                        setDashboardLastUpdated('User list loaded at ' + new Date().toLocaleTimeString());
                    } catch(e) {
                        console.error('Error loading users:', e);
                        setDashboardLastUpdated('User list parse failed');
                    }
                } else {
                    setDashboardLastUpdated('User list request failed');
                }
            }
        };
        xhr.ontimeout = function() {
            var tbody = document.getElementById('pppoe-tbody');
            requestState.userList = false;
            if (tbody && allUsers.length === 0) tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:40px;color:#f59e0b;"><i class="fa fa-exclamation-triangle"></i> User list temporarily unavailable (router slow or unreachable — try PPPoE tab if users use PPPoE)</td></tr>';
            setDashboardLastUpdated('User list timeout');
        };
        xhr.onerror = function() {
            requestState.userList = false;
            setDashboardLastUpdated('User list request failed');
        };
        xhr.send();
    }

    function processUserListData(users) {
        users = users.filter(function(u) {
            return u.username && u.username !== 'null' && u.username !== 'NULL' && u.username.trim() !== '' && typeof u.username === 'string';
        });
        if (lastUserCount !== users.length) { lastUserCount = users.length; ClientCache.clear(); }
        allUsers = users.slice();
        // Jangan reset sort state agar pilihan sort user tidak hilang saat refresh
        if (mobileSortColumn === null || mobileSortColumn === undefined) {
            mobileSortColumn = 'usage';
            mobileSortDirection = 'desc';
        }
        searchUsers();
    }

    function updateBandwidthCard(uploadStr, downloadStr) {
        var u = document.getElementById('bandwidth-upload');
        var d = document.getElementById('bandwidth-download');
        if (u) u.textContent = (uploadStr || '—') + ' ↑';
        if (d) d.textContent = (downloadStr || '—') + ' ↓';
    }

    function setSystemMonitoringUnavailable(message) {
        var uptimeDetail = document.getElementById('uptime-detail');
        if (uptimeDetail) uptimeDetail.textContent = message || 'Router data unavailable';
        setDashboardLastUpdated('System status stale');
    }

    function setUserCountsUnavailable() {
        setDashboardLastUpdated('User stats stale');
    }

    function setInterfaceUnavailable(message) {
        var sel = document.getElementById('interfaceSelect');
        if (sel && !interfaceData.interfaces.length) {
            sel.innerHTML = '<option value="">' + (message || 'Router unavailable') + '</option>';
        }
        setInterfaceLastUpdated(message || 'Router unavailable');
    }

    function formatSpeed(bytesPerSecond) {
        var units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
        var val = Math.max(bytesPerSecond, 0);
        var pow = Math.floor(val ? Math.log(val) / Math.log(1024) : 0);
        pow = Math.min(pow, units.length - 1);
        val = val / Math.pow(1024, pow);
        return val.toFixed(2) + ' ' + units[pow];
    }

    function getCurrentServiceLabel() {
        return currentUserService === 'pppoe' ? 'PPPoE' : 'Hotspot';
    }

    function updateCountCardLocally(service, delta) {
        var countId = service === 'pppoe' ? 'pppoe-count-live-2' : 'pppoe-count-live';
        var detailId = service === 'pppoe' ? 'pppoe-status-detail' : 'hotspot-status-detail';
        var countEl = document.getElementById(countId);
        var detailEl = document.getElementById(detailId);
        if (!countEl) return;

        var current = parseInt((countEl.textContent || '').replace(/[^0-9-]/g, ''), 10);
        if (isNaN(current)) return;

        var next = Math.max(0, current + delta);
        countEl.textContent = String(next);

        if (detailEl) {
            var detailHtml = detailEl.innerHTML;
            detailHtml = detailHtml.replace(/(>)(\d+)(\s+Online<)/, '$1' + next + '$3');
            detailEl.innerHTML = detailHtml;
        }
    }

    function removeUserLocally(username) {
        allUsers = allUsers.filter(function(user) { return user.username !== username; });
        filteredUsers = filteredUsers.filter(function(user) { return user.username !== username; });
        calculatePagination();
        displayUsers(displayedUsers);
        updateSearchStats(allUsers.length, filteredUsers.length);
    }

    function verifyUserDisconnect(username, service) {
        var xhr = new XMLHttpRequest();
        var url = buildApiUrl('user_monitor_ui') + '&action=get_user_presence&service=' + encodeURIComponent(service) + '&username=' + encodeURIComponent(username);
        xhr.open('GET', url, true);
        xhr.timeout = getMikrotikXhrTimeout();
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4 || xhr.status !== 200) return;
            try {
                var response = JSON.parse(xhr.responseText);
                if (!response.success) {
                    return;
                }
                if (response.online) {
                    loadUserList();
                    updateUserCounts();
                    showNotification('warning', 'User Still Online', username + ' is still active on the router. The table has been refreshed.');
                }
            } catch (e) {}
        };
        xhr.send();
    }

    function displayUsers(users) {
        var tbody = document.getElementById('pppoe-tbody');
        var mobileContainer = document.getElementById('mobileUserList');
        if (!tbody || !mobileContainer) return;

        if (users.length === 0) {
            var st = document.getElementById('searchUsername').value;
            var msg = st ? 'No users found for "' + st + '"' : 'No ' + getCurrentServiceLabel() + ' users online';
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:40px;color:#94a3b8;">' + msg + '</td></tr>';
            mobileContainer.innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;">' + msg + '</div>';
            return;
        }

        var batchSize = PerformanceConfig.batchSize;
        var currentBatch = 0;
        var desktopHTML = '';
        var mobileHTML = '';

        function processBatch() {
            var start = currentBatch * batchSize;
            var end = Math.min(start + batchSize, users.length);
            for (var i = start; i < end; i++) {
                var user = users[i];
                var safe = user.username.replace(/[^a-zA-Z0-9_-]/g, '_');
                desktopHTML += '<tr id="user-' + safe + '">' +
                    '<td style="font-weight:600;color:#1e293b;">' + highlightSearchTerm(user.username) + '</td>' +
                    '<td style="max-width:140px;word-wrap:break-word;color:#64748b;font-size:12px;">' + (user.user_comment||'-') + '</td>' +
                    '<td>' + createClickableIP(user.address, user.username) + '</td>' +
                    '<td style="font-size:12px;color:#64748b;">' + (user.uptime||'-') + '</td>' +
                    '<td class="usage-cell mm-usage">' + (user.total_usage||'-') + '</td>' +
                    '<td style="text-align:center;"><button class="mm-btn-traffic" onclick="openTrafficModal(\'' + user.username + '\', \'' + (user.user_comment||'').replace(/\'/g,"\\'") + '\', \'' + (user.queue_limit||'') + '\', \'' + currentUserService + '\')"><i class="fa fa-line-chart"></i> Live Monitor</button></td>' +
                    '<td style="text-align:center;white-space:nowrap;">' +
                    (currentUserService === 'pppoe' ? '<button onclick="resetUser(\'' + user.username + '\')" class="mm-btn mm-btn-danger mm-btn-xs" title="Reset Usage" style="margin-right:4px;"><i class="fa fa-refresh"></i></button>' : '') +
                    '<button onclick="removeUser(\'' + user.username + '\', \'' + currentUserService + '\')" class="mm-btn mm-btn-ghost mm-btn-xs" title="Disconnect" style="border:1px solid #f59e0b;color:#b45309;"><i class="fa fa-power-off"></i></button>' +
                    '</td>' +
                    '</tr>';
                mobileHTML += createMobileCardWithSearch(user);
            }
            currentBatch++;
            if (end < users.length) {
                setTimeout(processBatch, PerformanceConfig.batchDelay);
            } else {
                tbody.innerHTML = desktopHTML;
                mobileContainer.innerHTML = mobileHTML;
            }
        }
        processBatch();
    }

    function highlightSearchTerm(username) {
        var term = document.getElementById('searchUsername').value.toLowerCase().trim();
        if (!term) return username;
        var regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return username.replace(regex, '<mark style="background:#fef08a;border-radius:2px;padding:0 1px;">$1</mark>');
    }

    function createMobileCardWithSearch(user) {
        var safe = user.username.replace(/[^a-zA-Z0-9_-]/g, '_');
        var highlighted = highlightSearchTerm(user.username);
        return '<div class="mm-mobile-card">' +
            '<div class="mm-mobile-card-header">' +
            '<span style="font-weight:700;color:#1e293b;">' + highlighted + '</span>' +
            '<span class="mm-badge mm-badge-success"><i class="fa fa-circle" style="font-size:8px;"></i> ' + (user.status||'Online') + '</span>' +
            '</div>' +
            '<div class="mm-mobile-card-body">' +
            '<div class="mm-mobile-row"><span class="mm-mobile-label">Comment</span><span class="mm-mobile-value">' + (user.user_comment||'-') + '</span></div>' +
            '<div class="mm-mobile-row"><span class="mm-mobile-label">IP Address</span><span class="mm-mobile-value">' + createClickableIP(user.address, user.username) + '</span></div>' +
            '<div class="mm-mobile-row"><span class="mm-mobile-label">Uptime</span><span class="mm-mobile-value">' + (user.uptime||'-') + '</span></div>' +
            '<div class="mm-mobile-row"><span class="mm-mobile-label">Total Usage</span><span class="mm-mobile-value mm-usage usage-mobile-' + safe + '">' + (user.total_usage||'-') + '</span></div>' +
            '<div class="mm-mobile-row"><span class="mm-mobile-label">Traffic</span><span class="mm-mobile-value">' +
            '<button class="mm-btn-traffic" style="width:100%;" onclick="openTrafficModal(\'' + user.username + '\', \'' + (user.user_comment||'').replace(/\'/g,"\\'") + '\', \'\', \'' + currentUserService + '\')"><i class="fa fa-line-chart"></i> Live Monitor</button>' +
            '</span></div>' +
                '<div class="mm-mobile-row"><span class="mm-mobile-label">Actions</span><span class="mm-mobile-value">' +
            (currentUserService === 'pppoe' ? '<button onclick="resetUser(\'' + user.username + '\')" class="mm-btn mm-btn-danger mm-btn-xs" style="margin-right:4px;"><i class="fa fa-refresh"></i> Reset</button>' : '') +
            '<button onclick="removeUser(\'' + user.username + '\', \'' + currentUserService + '\')" class="mm-btn mm-btn-ghost mm-btn-xs" style="border:1px solid #f59e0b;color:#b45309;"><i class="fa fa-power-off"></i> Disconnect</button>' +
            '</span></div>' +
            '</div></div>';
    }

    function autoCleanup() {
        if (dashboardTimers.cleanup) clearInterval(dashboardTimers.cleanup);
        dashboardTimers.cleanup = setInterval(function() { ClientCache.clear(); }, 300000);
    }

    // ===================================================
    // SEARCH & PAGINATION
    // ===================================================
    function searchUsers() {
        // Simpan filter dashboard ke sessionStorage
        var ds = document.getElementById('searchUsername');
        var rp = document.getElementById('rowsPerPage');
        sessionStorage.setItem('data_usage_dashboard_filter', JSON.stringify({
            search  : ds ? ds.value : '',
            perPage : rp ? rp.value : '10'
        }));

        var ds = document.getElementById('searchUsername');
        var ms = document.getElementById('mobileSearchUsername');
        var term = '';
        if (ds && ds.value) term = ds.value.toLowerCase().trim();
        else if (ms && ms.value) term = ms.value.toLowerCase().trim();

        filteredUsers = term === '' ? allUsers.slice() : allUsers.filter(function(u) {
            var username = (u.username || '').toLowerCase();
            var comment = (u.user_comment || '').toLowerCase();
            var address = (u.address || '').toLowerCase();
            return username.includes(term) || comment.includes(term) || address.includes(term);
        });

        if (sortColumn) sortUserArray(filteredUsers, sortColumn, sortDirection);
        else if (mobileSortColumn) sortUserArray(filteredUsers, mobileSortColumn, mobileSortDirection);

        currentPage = 1;
        calculatePagination();
        displayUsers(displayedUsers);
        updateSearchStats(allUsers.length, filteredUsers.length);
    }

    function updateSearchStats(total, filtered) {
        var ids = ['totalUsers', 'totalUsersMobile'];
        var fids = ['filteredUsers', 'filteredUsersMobile'];
        ids.forEach(function(id) { var el = document.getElementById(id); if (el) el.textContent = total; });
        fids.forEach(function(id) { var el = document.getElementById(id); if (el) el.textContent = filtered; });
    }

    function calculatePagination() {
        totalPages = Math.ceil(filteredUsers.length / rowsPerPage) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        var start = (currentPage - 1) * rowsPerPage;
        var end = Math.min(start + rowsPerPage, filteredUsers.length);
        displayedUsers = filteredUsers.slice(start, end);
        updatePaginationInfo();
        updatePaginationButtons();
    }

    function updatePaginationInfo() {
        var start = filteredUsers.length ? ((currentPage - 1) * rowsPerPage + 1) : 0;
        var end = filteredUsers.length ? Math.min(currentPage * rowsPerPage, filteredUsers.length) : 0;
        var info = document.getElementById('paginationInfo');
        if (info) info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + filteredUsers.length + ' entries';
        var minfo = document.getElementById('mobilePaginationInfo');
        if (minfo) minfo.textContent = currentPage + ' of ' + totalPages;
    }

    function updatePaginationButtons() {
        var ids = ['firstBtn','prevBtn','nextBtn','lastBtn'];
        var states = [currentPage===1, currentPage===1, currentPage===totalPages, currentPage===totalPages];
        ids.forEach(function(id, i) {
            var btn = document.getElementById(id);
            if (btn) btn.disabled = states[i];
        });
        var mprev = document.getElementById('mobilePrevBtn');
        var mnext = document.getElementById('mobileNextBtn');
        if (mprev) mprev.disabled = currentPage === 1;
        if (mnext) mnext.disabled = currentPage === totalPages;
        var lastBtn = document.getElementById('lastBtn');
        if (lastBtn) lastBtn.onclick = function() { goToPage(totalPages); };
        updatePageNumbers();
    }

    function updatePageNumbers() {
        var container = document.getElementById('pageNumbers');
        if (!container) return;
        container.innerHTML = '';
        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, currentPage + 2);
        for (var i = start; i <= end; i++) {
            var btn = document.createElement('button');
            btn.className = 'mm-pagination-btn' + (i === currentPage ? ' active' : '');
            btn.textContent = i;
            btn.onclick = (function(p) { return function() { goToPage(p); }; })(i);
            container.appendChild(btn);
        }
    }

    function goToPage(page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        calculatePagination();
        displayUsers(displayedUsers);
    }
    function nextPage()     { if (currentPage < totalPages) goToPage(currentPage + 1); }
    function previousPage() { if (currentPage > 1)          goToPage(currentPage - 1); }

    function changeRowsPerPage() {
        var s = document.getElementById('rowsPerPage');
        var ms = document.getElementById('mobileRowsPerPage');
        rowsPerPage = parseInt(s.value);
        if (ms) ms.value = s.value;
        currentPage = 1;
        calculatePagination();
        displayUsers(displayedUsers);
    }

    function changeMobileRowsPerPage() {
        var ms = document.getElementById('mobileRowsPerPage');
        var s = document.getElementById('rowsPerPage');
        rowsPerPage = parseInt(ms.value);
        if (s) s.value = ms.value;
        currentPage = 1;
        calculatePagination();
        displayUsers(displayedUsers);
    }

    // ===================================================
    // MOBILE SORT
    // ===================================================
    function sortMobileData(column) {
        if (mobileSortColumn === column) mobileSortDirection = mobileSortDirection === 'asc' ? 'desc' : 'asc';
        else { mobileSortDirection = column === 'usage' ? 'desc' : 'asc'; mobileSortColumn = column; }
        sortUserArray(filteredUsers, column, mobileSortDirection);
        currentPage = 1;
        calculatePagination();
        displayUsers(displayedUsers);
        updateMobileSortIndicators(column, mobileSortDirection);
    }

    function sortUserArray(users, column, direction) {
        users.sort(function(a, b) {
            var aVal, bVal, cmp = 0;
            switch (column) {
                case 'username': aVal = a.username.toLowerCase(); bVal = b.username.toLowerCase(); cmp = aVal.localeCompare(bVal); break;
                case 'ip':       aVal = ipToNumber(a.address||'0.0.0.0'); bVal = ipToNumber(b.address||'0.0.0.0'); cmp = aVal - bVal; break;
                case 'usage':    aVal = usageToBytes(a.total_usage||'0 B'); bVal = usageToBytes(b.total_usage||'0 B'); cmp = aVal - bVal; break;
                default: return 0;
            }
            return direction === 'asc' ? cmp : -cmp;
        });
    }

    function updateMobileSortIndicators(activeColumn, direction) {
        ['username-indicator','ip-indicator','usage-indicator'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.textContent = '';
        });
        document.querySelectorAll('.mobile-sort-btn').forEach(function(btn) { btn.classList.remove('active'); });
        var activeEl = document.getElementById(activeColumn + '-indicator');
        var activeBtn = document.getElementById('sortMobile' + (activeColumn === 'username' ? 'Username' : activeColumn === 'ip' ? 'IP' : 'Usage'));
        if (activeEl) activeEl.textContent = direction === 'asc' ? ' ↑' : ' ↓';
        if (activeBtn) activeBtn.classList.add('active');
    }

    function initializeMobileSort() { updateMobileSortIndicators(mobileSortColumn, mobileSortDirection); }

    // ===================================================
    // USER ACTIONS
    // ===================================================
    function resetUser(username) {
        if (!confirm('Reset total usage for ' + username + '?')) return;
        var btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner mm-spin"></i>';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-refresh"></i>';
                if (xhr.status === 200) {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) { showNotification('success', 'Reset Successful', 'Usage data for ' + username + ' has been reset'); setTimeout(loadUserList, 1000); }
                        else showNotification('error', 'Reset Failed', r.error);
                    } catch(e) {}
                }
            }
        };
        xhr.send('reset_action=reset_interface&username=' + encodeURIComponent(username));
    }

    function removeUser(username, service) {
        service = service || currentUserService;
        if (!confirm('Disconnect ' + service.toUpperCase() + ' user ' + username + '? They will need to reconnect manually.')) return;
        var btn = event.target;
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner mm-spin"></i>';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btn.disabled = false;
                btn.innerHTML = orig;
                if (xhr.status === 200) {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            showNotification('success', 'User Disconnected', username + ' has been kicked offline');
                            ClientCache.clear();
                            removeUserLocally(username);
                            updateCountCardLocally(service, -1);
                            setTimeout(function() { verifyUserDisconnect(username, service); }, 1500);
                            setTimeout(function() { updateUserCounts(); }, 1200);
                        } else showNotification('error', 'Disconnect Failed', r.error);
                    } catch(e) {}
                }
            }
        };
        xhr.send('remove_action=remove_interface&username=' + encodeURIComponent(username) + '&service=' + encodeURIComponent(service));
    }

    // ===================================================
    // SYSTEM MONITORING
    // ===================================================
    function updateSystemMonitoring() {
        if (!canRunRealtime()) return;
        if (requestState.system) return;
        requestState.system = true;
        var xhr = trackXhr(new XMLHttpRequest());
        var url = buildApiUrl('user_monitor_ui') + '&action=system_resources';
        xhr.open('GET', url, true);
        xhr.timeout = getMikrotikXhrTimeout();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                requestState.system = false;
                // Detect if redirected (HTML response = redirect to login)
                if (xhr.responseText && (xhr.responseText.startsWith('<!DOCTYPE') || xhr.responseText.startsWith('<html'))) {
                    pauseRealtimeActivity();
                    return;
                }
                if (xhr.status === 200) {
                    try {
                        var text = xhr.responseText.trim();
                        if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                            setSystemMonitoringUnavailable('Unexpected HTML response');
                            scheduleSystemPoll(45000);
                            return;
                        }
                        var data = JSON.parse(text);

                        var upd = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };

                        if (data.error) {
                            setSystemMonitoringUnavailable(data.error);
                            scheduleSystemPoll(45000);
                            return;
                        }

                        var cpuLoad = typeof data.cpu_load === 'number' ? data.cpu_load : parseFloat(data.cpu_load);
                        var memoryPercent = typeof data.memory_percent === 'number' ? data.memory_percent : parseFloat(data.memory_percent);
                        var memoryUsed = data.memory_used || '—';
                        var memoryTotal = data.memory_total || '—';

                        upd('cpu-load', isNaN(cpuLoad) ? '—' : cpuLoad + '%');
                        if (data.health && (data.health['cpu-temperature'] || data.health['temperature'])) {
                            var temp = data.health['cpu-temperature'] || data.health['temperature'];
                            upd('cpu-temp', 'Temp: ' + temp.value + '°C');
                        } else {
                            upd('cpu-temp', 'Temp: —');
                        }
                        upd('memory-usage', isNaN(memoryPercent) ? '—' : memoryPercent + '%');
                        upd('memory-detail', memoryUsed + ' / ' + memoryTotal);

                        if (data.uptime) {
                            var fmt = formatUptime(data.uptime);
                            upd('uptime-display', fmt.short);
                            upd('uptime-detail', fmt.full);
                        } else {
                            upd('uptime-display', '—');
                            upd('uptime-detail', 'Router uptime unavailable');
                        }

                        var uploadVal   = (data.current_upload   && data.current_upload   !== '0 B/s') ? data.current_upload   : null;
                        var downloadVal = (data.current_download && data.current_download !== '0 B/s') ? data.current_download : null;
                        updateBandwidthCard(uploadVal, downloadVal);
                        setDashboardLastUpdated('System synced at ' + new Date().toLocaleTimeString());
                        scheduleSystemPoll(30000);

                    } catch(e) {
                        setSystemMonitoringUnavailable('Failed to parse router status');
                        console.error('System monitoring error:', e);
                        scheduleSystemPoll(45000);
                    }
                } else {
                    scheduleSystemPoll(45000);
                }
            }
        };
        xhr.ontimeout = function() {
            requestState.system = false;
            setSystemMonitoringUnavailable('Router request timeout');
            scheduleSystemPoll(45000);
        };
        xhr.onerror = function() {
            requestState.system = false;
            setSystemMonitoringUnavailable('Router request failed');
            scheduleSystemPoll(45000);
        };
        xhr.send();
    }

    function updateUserCounts(onDone) {
        function finish() {
            if (typeof onDone === 'function') {
                try { onDone(); } catch (e) {}
            }
        }

        if (!canRunRealtime()) {
            finish();
            return;
        }
        if (requestState.counts) {
            if (typeof onDone === 'function') {
                setTimeout(function() { updateUserCounts(onDone); }, 400);
            }
            return;
        }
        requestState.counts = true;
        var xhr = trackXhr(new XMLHttpRequest());
        var url = buildApiUrl('user_monitor_ui') + '&action=get_user_counts';
        xhr.open('GET', url, true);
        xhr.timeout = getMikrotikXhrTimeout();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                requestState.counts = false;
                // Detect if redirected (HTML response = redirect to login)
                if (xhr.responseText && (xhr.responseText.startsWith('<!DOCTYPE') || xhr.responseText.startsWith('<html'))) {
                    pauseRealtimeActivity();
                    scheduleCountsPoll(30000);
                    finish();
                    return;
                }
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            var upd = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
                            var updHtml = function(id, html) { var el = document.getElementById(id); if (el) el.innerHTML = html; };
                            
                            var regHotspot = data.reg_hotspot || data.total_hotspot || 0;
                            var offHotspot = Math.max(0, regHotspot - data.total_hotspot);
                            
                            var regPPPoE = data.reg_pppoe || data.total_pppoe || 0;
                            var offPPPoE = Math.max(0, regPPPoE - data.total_pppoe);

                            upd('pppoe-count-live', data.total_hotspot);
                            updHtml('hotspot-status-detail', 
                                '<span style="color:#22c55e;">' + data.total_hotspot + ' Online</span> / ' + 
                                '<span style="color:#94a3b8;">' + offHotspot + ' Offline</span>'
                            );

                            upd('pppoe-count-live-2', data.total_pppoe);
                            updHtml('pppoe-status-detail', 
                                '<span style="color:#ef4444;">' + data.total_pppoe + ' Online</span> / ' + 
                                '<span style="color:#94a3b8;">' + offPPPoE + ' Offline</span>'
                            );
                            maybeAutoSelectUserServiceFromRouterCounts(data);
                            setDashboardLastUpdated('Stats synced at ' + new Date().toLocaleTimeString());
                        } else {
                            setUserCountsUnavailable();
                        }
                    } catch(e) {
                        setUserCountsUnavailable();
                    }
                } else {
                    setUserCountsUnavailable();
                }
                scheduleCountsPoll(30000);
                finish();
            }
        };
        xhr.ontimeout = function() {
            requestState.counts = false;
            setUserCountsUnavailable();
            scheduleCountsPoll(45000);
            finish();
        };
        xhr.onerror = function() {
            requestState.counts = false;
            setUserCountsUnavailable();
            scheduleCountsPoll(45000);
            finish();
        };
        xhr.send();
    }

    function formatUptime(str) {
        var days = 0, hours = 0, minutes = 0;
        var wm = str.match(/(\d+)w/); var dm = str.match(/(\d+)d/);
        var hm = str.match(/(\d+)h/); var mm = str.match(/(\d+)m/);
        if (wm) days += parseInt(wm[1]) * 7;
        if (dm) days += parseInt(dm[1]);
        if (hm) hours = parseInt(hm[1]);
        if (mm) minutes = parseInt(mm[1]);
        var short = days > 0 ? days + 'd' : hours > 0 ? hours + 'h' : minutes + 'm';
        var full = '';
        if (days > 0)    full += days + ' days ';
        if (hours > 0)   full += hours + ' hours ';
        if (minutes > 0) full += minutes + ' minutes';
        return { short: short, full: full.trim() || 'Just started' };
    }

    function initializeMonitoring() {
        if (!canRunRealtime()) return;
        scheduleSystemPoll(0);
        scheduleCountsPoll(0);
    }

    // ===================================================
    // INTERFACE MONITORING
    // ===================================================
    var interfaceData = { interfaces: [], selectedInterface: '', trafficInterval: null };

    function loadInterfaces() {
        if (!canRunRealtime()) return;
        var xhr = trackXhr(new XMLHttpRequest());
        var url = buildApiUrl('user_monitor_ui') + '&action=get_interfaces';
        xhr.open('GET', url, true);
        xhr.timeout = getMikrotikXhrTimeout();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                // Detect if redirected (HTML response = redirect to login)
                if (xhr.responseText && (xhr.responseText.startsWith('<!DOCTYPE') || xhr.responseText.startsWith('<html'))) {
                    pauseRealtimeActivity();
                    return;
                }
                if (xhr.status === 200) {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            interfaceData.interfaces = r.interfaces;
                            populateInterfaceSelect();
                        } else {
                            setInterfaceUnavailable(r.error || 'Router interfaces unavailable');
                        }
                    } catch(e) {
                        setInterfaceUnavailable('Failed to parse interfaces');
                    }
                }
            }
        };
        xhr.ontimeout = function() {
            setInterfaceUnavailable('Interface request timeout');
        };
        xhr.onerror = function() {
            setInterfaceUnavailable('Interface request failed');
        };
        xhr.send();
    }

    function populateInterfaceSelect() {
        var sel = document.getElementById('interfaceSelect');
        if (!sel) return;
        sel.innerHTML = '<option value="">Select Interface...</option>';
        if (!interfaceData.interfaces || interfaceData.interfaces.length === 0) {
            sel.innerHTML = '<option value="">No interfaces available</option>';
            resetInterfaceData();
            return;
        }
        interfaceData.interfaces.forEach(function(iface) {
            var opt = document.createElement('option');
            opt.value = iface.name;
            if (iface.status !== 'running') {
                opt.disabled = true;
                opt.textContent = '⊘ ' + iface.name + ' (' + iface.description + ') [Disabled]';
                opt.style.color = '#94a3b8';
                opt.style.backgroundColor = '#f1f5f9';
                opt.style.fontStyle = 'italic';
            } else {
                opt.textContent = '● ' + iface.name + ' (' + iface.description + ')';
                opt.style.color = '#1e293b';
                opt.style.fontWeight = '500';
            }
            sel.appendChild(opt);
        });
        var first = interfaceData.interfaces.find(function(i) { return i.status === 'running'; });
        if (first) { sel.value = first.name; selectInterface(); }
    }

    function selectInterface() {
        var sel = document.getElementById('interfaceSelect');
        var name = sel.value;
        if (!name) { resetInterfaceData(); return; }
        interfaceData.selectedInterface = name;
        startInterfaceMonitoring();
    }

    function resetInterfaceData() {
        if (interfaceData.trafficInterval) { clearInterval(interfaceData.trafficInterval); interfaceData.trafficInterval = null; }
        var upd = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        upd('rx-speed', '-'); upd('tx-speed', '-');
        upd('rx-total', 'Total: -'); upd('tx-total', 'Total: -');
        var rp = document.getElementById('rx-progress'); if (rp) rp.style.width = '0%';
        var tp = document.getElementById('tx-progress'); if (tp) tp.style.width = '0%';
        upd('interface-packets-rx', '-'); upd('interface-packets-tx', '-');
        upd('interface-link-speed', '-'); upd('interface-mtu', '-');
    }

    function startInterfaceMonitoring() {
        if (!canRunRealtime()) return;
        if (interfaceData.trafficInterval) clearInterval(interfaceData.trafficInterval);
        loadInterfaceTraffic();
        interfaceData.trafficInterval = setInterval(loadInterfaceTraffic, 10000);
    }

    function loadInterfaceTraffic() {
        if (!canRunRealtime()) return;
        if (!interfaceData.selectedInterface) return;
        var xhr = trackXhr(new XMLHttpRequest());
        var url = buildApiUrl('user_monitor_ui') + '&action=get_interface_traffic&interface=' + encodeURIComponent(interfaceData.selectedInterface);
        xhr.open('GET', url, true);
        xhr.timeout = getMikrotikXhrTimeout();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.success) {
                        updateInterfaceDisplay(r.data);
                        setInterfaceLastUpdated(new Date().toLocaleTimeString());
                    } else {
                        setInterfaceUnavailable(r.error || 'Router interface unavailable');
                    }
                } catch(e) {
                    setInterfaceUnavailable('Failed to parse interface traffic');
                }
            }
        };
        xhr.ontimeout = function() {
            setInterfaceUnavailable('Interface traffic timeout');
        };
        xhr.onerror = function() {
            setInterfaceUnavailable('Interface traffic request failed');
        };
        xhr.send();
    }

    function updateInterfaceDisplay(data) {
        if (!data) { resetInterfaceData(); return; }
        var upd = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        upd('rx-speed', data.rx_rate.toFixed(1) + ' Mbps');
        upd('tx-speed', data.tx_rate.toFixed(1) + ' Mbps');
        upd('rx-total', 'Total: ' + data.rx_total);
        upd('tx-total', 'Total: ' + data.tx_total);
        var rp = document.getElementById('rx-progress'); if (rp) rp.style.width = Math.min(data.rx_percentage, 100) + '%';
        var tp = document.getElementById('tx-progress'); if (tp) tp.style.width = Math.min(data.tx_percentage, 100) + '%';
        upd('interface-packets-rx', data.rx_packets);
        upd('interface-packets-tx', data.tx_packets);
        upd('interface-link-speed', data.link_speed);
        upd('interface-mtu', data.mtu);
    }

    // ===================================================
    // NOTIFICATION
    // ===================================================
    function showNotification(type, title, message) {
        document.querySelectorAll('.mm-notification').forEach(function(n) { n.remove(); });
        var types = {
            success: { cls: 'mm-notification-success', icon: '✓' },
            error:   { cls: 'mm-notification-error',   icon: '✕' },
            warning: { cls: 'mm-notification-warning',  icon: '⚠' },
            info:    { cls: 'mm-notification-info',     icon: 'ℹ' }
        };
        var t = types[type] || types.info;
        var el = document.createElement('div');
        el.className = 'mm-notification ' + t.cls;
        el.innerHTML = '<div style="font-size:18px;font-weight:700;flex-shrink:0;">' + t.icon + '</div>' +
            '<div style="flex:1;"><div style="font-weight:600;font-size:14px;margin-bottom:2px;">' + title + '</div>' +
            (message ? '<div style="font-size:12px;opacity:0.8;">' + message + '</div>' : '') + '</div>' +
            '<button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:18px;opacity:0.6;padding:0;flex-shrink:0;">✕</button>';
        document.body.appendChild(el);
        setTimeout(function() { if (el.parentElement) el.style.animation = 'slideInRight 0.3s ease reverse'; setTimeout(function() { if (el.parentElement) el.remove(); }, 300); }, 5000);
    }

    // ===================================================
    // ROUTER SWITCH
    // ===================================================
    function switchRouter() {
        var sel = document.getElementById('routerSelect');
        var id = sel.value;
        var pppoeEl = document.getElementById('pppoe-count');
        if (pppoeEl) pppoeEl.textContent = '...';

        var currentUrl = window.location.href;
        var newUrl;
        if (currentUrl.includes('?_route=')) {
            newUrl = window.location.origin + window.location.pathname + '?_route=plugin/user_monitor_ui/' + id;
        } else {
            var pathParts = window.location.pathname.split('/');
            if (pathParts[pathParts.length - 1] && pathParts[pathParts.length - 1].match(/^\d+$/)) {
                pathParts[pathParts.length - 1] = id;
            } else {
                pathParts.push(id);
            }
            newUrl = window.location.origin + pathParts.join('/');
        }
        window.location.href = newUrl;
    }

    // ===================================================
    // AUTO START
    // ===================================================
    function autoStart() {
        var noRouter = document.querySelector('.panel-warning');
        if (noRouter && noRouter.textContent.includes('No MikroTik Router')) return;
        realtimeState.started = true;

        var routerSel = document.getElementById('routerSelect');
        if (routerSel) currentRouterId = routerSel.value;

        var upd = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        upd('pppoe-count-live', '...'); upd('pppoe-count-live-2', '...');

        restoreUserServicePreference();

        // Prioritas pertama: config & UI (ringan, ke database)
        initializeTabs();

        // Restore filter dashboard jika ini refresh
        var isRefresh = (performance && performance.navigation && performance.navigation.type === 1)
            || (performance && performance.getEntriesByType
                && performance.getEntriesByType('navigation')[0]
                && performance.getEntriesByType('navigation')[0].type === 'reload');

        if (isRefresh) {
            var savedDashFilter = sessionStorage.getItem('data_usage_dashboard_filter');
            if (savedDashFilter) {
                try {
                    var f = JSON.parse(savedDashFilter);
                    var ds = document.getElementById('searchUsername');
                    var ms = document.getElementById('mobileSearchUsername');
                    var rp = document.getElementById('rowsPerPage');
                    if (ds && f.search) ds.value = f.search;
                    if (ms && f.search) ms.value = f.search;
                    if (rp && f.perPage) { rp.value = f.perPage; rowsPerPage = parseInt(f.perPage); }
                } catch(e) {}
            }
        } else {
            // Navigasi baru — clear semua filter sessionStorage
            sessionStorage.removeItem('data_usage_history_filter');
            sessionStorage.removeItem('data_usage_dashboard_filter');
        }
        initializeMobileSort();
        refreshServiceTabUi();
        autoCleanup();

        // Dashboard data loads when user clicks the Dashboard tab (via switchTab)
        // This prevents auto-loading on page load which causes server overload
    }

    // ===================================================
    // EVENT LISTENERS
    // ===================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ds = document.getElementById('searchUsername');
        var cs = document.getElementById('clearSearch');
        var ms = document.getElementById('mobileSearchUsername');
        var mcs = document.getElementById('mobileClearSearch');

        if (ds) {
            ds.addEventListener('input', function() { if (ms) ms.value = this.value; searchUsers(); });
            ds.addEventListener('keypress', function(e) { if (e.key === 'Enter') searchUsers(); });
        }
        if (cs) cs.addEventListener('click', function() { if (ds) ds.value = ''; if (ms) ms.value = ''; searchUsers(); if (ds) ds.focus(); });
        if (ms) {
            ms.addEventListener('input', function() { if (ds) ds.value = this.value; searchUsers(); });
            ms.addEventListener('keypress', function(e) { if (e.key === 'Enter') searchUsers(); });
        }
        if (mcs) mcs.addEventListener('click', function() { if (ms) ms.value = ''; if (ds) ds.value = ''; searchUsers(); if (ms) ms.focus(); });
    });

    // Initialize
    if (typeof jQuery === 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoStart);
        else autoStart();
    } else {
        jQuery(document).ready(autoStart);
    }

</script>
{/literal}

<!-- ===== TRAFFIC MODAL ===== -->
<div id="trafficModal">
    <div class="tm-card">
        <div class="tm-header">
            <div>
                <h3><i class="fa fa-bar-chart"></i> Traffic Monitor</h3>
                <p id="tmUsername">—</p>
            </div>
            <button class="tm-close" onclick="closeTrafficModal()">×</button>
        </div>
        <div class="tm-body">
            <!-- MAIN SPEED DISPLAY: LARGE & FOCUSED -->
            <div class="tm-speed-main">
                <div class="tm-speed-primary upload">
                    <div class="tm-speed-icon"><i class="fa fa-arrow-up"></i></div>
                    <div class="tm-speed-label">Upload</div>
                    <div class="tm-speed-value" id="tmUploadVal">—</div>
                </div>
                <div class="tm-speed-primary download">
                    <div class="tm-speed-icon"><i class="fa fa-arrow-down"></i></div>
                    <div class="tm-speed-label">Download</div>
                    <div class="tm-speed-value" id="tmDownloadVal">—</div>
                </div>
            </div>
            <!-- TRAFFIC TREND GRAPH -->
            <div class="tm-chart-card">
                <div class="tm-chart-header">
                    <div class="tm-chart-title"><i class="fa fa-line-chart"></i> Traffic Trend (Last 18 Samples)</div>
                    <div class="tm-chart-legend">
                        <span class="tm-legend-item"><span class="tm-legend-swatch upload"></span>Upload</span>
                        <span class="tm-legend-item"><span class="tm-legend-swatch download"></span>Download</span>
                    </div>
                </div>
                <div class="tm-chart-shell">
                    <div class="tm-chart-ylabels">
                        <span id="tmYMax">0 bps</span>
                        <span id="tmYMid">0 bps</span>
                        <span id="tmYMin">0 bps</span>
                    </div>
                    <svg id="tmChartSvg" class="tm-chart-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="uploadGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" style="stop-color:#10b981;stop-opacity:0.2" />
                                <stop offset="100%" style="stop-color:#10b981;stop-opacity:0" />
                            </linearGradient>
                            <linearGradient id="downloadGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" style="stop-color:#2563eb;stop-opacity:0.2" />
                                <stop offset="100%" style="stop-color:#2563eb;stop-opacity:0" />
                            </linearGradient>
                        </defs>
                        <polygon id="tmUploadArea" fill="url(#uploadGradient)"></polygon>
                        <polygon id="tmDownloadArea" fill="url(#downloadGradient)"></polygon>
                        <polyline id="tmUploadLine" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points=""></polyline>
                        <polyline id="tmDownloadLine" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points=""></polyline>
                    </svg>
                    <div id="tmChartEmpty" class="tm-chart-empty">Waiting for live samples...</div>
                    <div class="tm-chart-xlabels">
                        <span id="tmXStart">—</span>
                        <span id="tmXEnd">—</span>
                    </div>
                </div>
                <div class="tm-chart-summary">
                    <div class="tm-summary-item">
                        <span class="tm-summary-label">Peak Upload</span>
                        <span class="tm-summary-value" id="tmPeakUpload">—</span>
                    </div>
                    <div class="tm-summary-item">
                        <span class="tm-summary-label">Peak Download</span>
                        <span class="tm-summary-value" id="tmPeakDownload">—</span>
                    </div>
                </div>
            </div>
            <div id="tmNoQueue" style="display:none;text-align:center;padding:10px;color:#f59e0b;font-size:13px;">
                <i class="fa fa-warning"></i> Queue tidak ditemukan untuk user ini
            </div>
        </div>
        <div class="tm-footer">
            <span><span class="tm-status-dot" id="tmDot"></span><span id="tmStatusText">Connecting...</span></span>
            <span id="tmTimestamp">—</span>
        </div>
    </div>
</div>

{literal}
<script>
var _tmInterval   = null;
var _tmUsername   = '';
var _tmMaxUpBps   = 0;
var _tmMaxDownBps = 0;
var _tmService    = 'hotspot';
var _tmHistoryUp  = [];
var _tmHistoryDown = [];
var _tmHistoryLabels = [];
var _tmMaxPoints = 18;

function tmFormatBps(bps) {
    if (!bps || bps <= 0) return '0 bps';
    var units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    var pow = Math.floor(Math.log(Math.max(bps, 1)) / Math.log(1000));
    pow = Math.min(pow, units.length - 1);
    return (Math.round((bps / Math.pow(1000, pow)) * 100) / 100) + ' ' + units[pow];
}

function tmResetChart() {
    _tmHistoryUp = [];
    _tmHistoryDown = [];
    _tmHistoryLabels = [];
    tmRenderChart();
}

function tmPushSample(uploadBps, downloadBps, label) {
    _tmHistoryUp.push(uploadBps || 0);
    _tmHistoryDown.push(downloadBps || 0);
    _tmHistoryLabels.push(label || '—');
    if (_tmHistoryUp.length > _tmMaxPoints) {
        _tmHistoryUp.shift();
        _tmHistoryDown.shift();
        _tmHistoryLabels.shift();
    }
    tmRenderChart();
}

function tmBuildPolyline(values, maxValue) {
    if (!values.length) return '';
    if (values.length === 1) {
        var y = 100 - ((values[0] / maxValue) * 100);
        return '0,' + y + ' 100,' + y;
    }

    var points = [];
    for (var i = 0; i < values.length; i++) {
        var x = (i / (values.length - 1)) * 100;
        var yPos = 100 - ((values[i] / maxValue) * 100);
        points.push(x.toFixed(2) + ',' + yPos.toFixed(2));
    }
    return points.join(' ');
}

function tmRenderChart() {
    var uploadLine = document.getElementById('tmUploadLine');
    var downloadLine = document.getElementById('tmDownloadLine');
    var chartEmpty = document.getElementById('tmChartEmpty');
    if (!uploadLine || !downloadLine || !chartEmpty) return;

    var maxValue = Math.max(1, Math.max.apply(null, _tmHistoryUp.concat(_tmHistoryDown).concat([0])));
    uploadLine.setAttribute('points', tmBuildPolyline(_tmHistoryUp, maxValue));
    downloadLine.setAttribute('points', tmBuildPolyline(_tmHistoryDown, maxValue));
    chartEmpty.style.display = _tmHistoryLabels.length ? 'none' : 'flex';

    var peakUp = Math.max.apply(null, _tmHistoryUp.concat([0]));
    var peakDown = Math.max.apply(null, _tmHistoryDown.concat([0]));
    var avgUp = _tmHistoryUp.length ? _tmHistoryUp.reduce(function(sum, val) { return sum + val; }, 0) / _tmHistoryUp.length : 0;
    var avgDown = _tmHistoryDown.length ? _tmHistoryDown.reduce(function(sum, val) { return sum + val; }, 0) / _tmHistoryDown.length : 0;

    document.getElementById('tmPeakUpload').textContent = tmFormatBps(peakUp);
    document.getElementById('tmPeakDownload').textContent = tmFormatBps(peakDown);
    document.getElementById('tmYMax').textContent = tmFormatBps(maxValue);
    document.getElementById('tmYMid').textContent = tmFormatBps(maxValue / 2);
    document.getElementById('tmYMin').textContent = '0 bps';

    var startLabel = _tmHistoryLabels.length ? _tmHistoryLabels[0] : '—';
    var midLabel = _tmHistoryLabels.length ? _tmHistoryLabels[Math.floor((_tmHistoryLabels.length - 1) / 2)] : '—';
    var endLabel = _tmHistoryLabels.length ? _tmHistoryLabels[_tmHistoryLabels.length - 1] : '—';
    document.getElementById('tmXStart').textContent = startLabel;
    document.getElementById('tmXEnd').textContent = endLabel;
}

function openTrafficModal(username, userComment, maxLimit, service) {
    _tmUsername   = username;
    _tmService    = service || currentUserService || 'hotspot';
    _tmMaxUpBps   = 0;
    _tmMaxDownBps = 0;
    if (maxLimit && maxLimit !== 'N/A' && maxLimit.indexOf('/') !== -1) {
        var parts     = maxLimit.split('/');
        _tmMaxUpBps   = parseInt(parts[0]) || 0;
        _tmMaxDownBps = parseInt(parts[1]) || 0;
    }
    document.getElementById('tmUsername').textContent    = userComment + ' (' + username + ')';
    document.getElementById('tmUploadVal').textContent   = '—';
    document.getElementById('tmDownloadVal').textContent = '—';
    document.getElementById('tmNoQueue').style.display   = 'none';
    tmResetChart();
    document.getElementById('tmDot').style.background    = '#f59e0b';
    document.getElementById('tmStatusText').textContent  = 'Connecting to selected user...';
    document.getElementById('tmTimestamp').textContent   = '—';
    document.getElementById('trafficModal').classList.add('open');
    document.body.classList.add('tm-modal-open');
    if (!canRunRealtime()) return;
    fetchUserTraffic();
    _tmInterval = setInterval(fetchUserTraffic, 2000);
}

function closeTrafficModal() {
    if (_tmInterval) { clearInterval(_tmInterval); _tmInterval = null; }
    _tmUsername = '';
    _tmService = 'hotspot';
    document.getElementById('trafficModal').classList.remove('open');
    document.body.classList.remove('tm-modal-open');
}

function fetchUserTraffic() {
    if (!canRunRealtime()) return;
    if (!_tmUsername) return;
    var url = buildApiUrl('user_monitor_ui') + '&action=get_user_realtime_traffic&service=' + encodeURIComponent(_tmService) + '&username=' + encodeURIComponent(_tmUsername);
    var xhr = trackXhr(new XMLHttpRequest());
    xhr.open('GET', url, true);
    xhr.timeout = (typeof getMikrotikXhrTimeout === 'function') ? getMikrotikXhrTimeout() : 35000;
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (!data.success) {
                    document.getElementById('tmDot').style.background   = '#ef4444';
                    document.getElementById('tmStatusText').textContent = 'Error: ' + (data.error || 'Unknown');
                    return;
                }
                if (!data.queue_found) {
                    document.getElementById('tmNoQueue').style.display  = 'block';
                    document.getElementById('tmDot').style.background   = '#f59e0b';
                    document.getElementById('tmStatusText').textContent = data.message || 'User is offline';
                    return;
                }
                document.getElementById('tmNoQueue').style.display     = 'none';
                document.getElementById('tmUploadVal').textContent     = data.upload;
                document.getElementById('tmDownloadVal').textContent   = data.download;
                tmPushSample(data.upload_bps || 0, data.download_bps || 0, data.timestamp || '—');

                // Jika _tmMaxUpBps belum diset dari luar, ambil dari max_limit response
                if (_tmMaxUpBps <= 0 && data.max_limit && data.max_limit.indexOf('/') !== -1) {
                    var limitParts = data.max_limit.split('/');
                    _tmMaxUpBps   = parseInt(limitParts[0]) || 0;
                    _tmMaxDownBps = parseInt(limitParts[1]) || 0;
                }

                document.getElementById('tmDot').style.background   = '#22c55e';
                document.getElementById('tmStatusText').textContent  = data.traffic_source === 'hotspot-active' ? 'Monitoring hotspot session' : 'Monitoring queue traffic';
                document.getElementById('tmTimestamp').textContent   = data.timestamp;
            } catch(e) {
                document.getElementById('tmDot').style.background   = '#ef4444';
                document.getElementById('tmStatusText').textContent = 'Parse error';
            }
        }
    };
    xhr.ontimeout = function() {
        document.getElementById('tmDot').style.background   = '#ef4444';
        document.getElementById('tmStatusText').textContent = 'Connection timeout';
    };
    xhr.send();
}

document.getElementById('trafficModal').addEventListener('click', function(e) {
    if (e.target === this) closeTrafficModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeTrafficModal(); closeUpdateModal(); closeUninstallModal(); }
});

document.addEventListener('visibilitychange', function() {
    realtimeState.pageVisible = !document.hidden;
    if (realtimeState.pageVisible) resumeRealtimeActivity();
    else pauseRealtimeActivity();
});

window.addEventListener('pagehide', function() {
    realtimeState.pageActive = false;
    pauseRealtimeActivity();
});

window.addEventListener('beforeunload', function() {
    realtimeState.pageActive = false;
    pauseRealtimeActivity();
});

// ===================================================
// UPDATE & UNINSTALL
// ===================================================
function mmToggleDotsMenu() {
    var menu = document.getElementById('mmDotsMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('click', function(e) {
    var btn  = document.getElementById('mmDotsBtn');
    var menu = document.getElementById('mmDotsMenu');
    if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});

function mikromonCheckUpdate() {
    var modal = document.getElementById('mikromonUpdateModal');
    modal.style.display = 'flex';
    document.getElementById('updateVersionInfo').style.display = 'none';
    document.getElementById('updateProgressLog').style.display = 'none';
    document.getElementById('btnRunUpdate').style.display = 'none';
    document.getElementById('updateStatusMsg').style.display = 'block';
    document.getElementById('updateStatusMsg').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Checking for updates...';
    document.getElementById('updateIcon').className = 'fa fa-spinner fa-spin';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            document.getElementById('updateIcon').className = 'fa fa-refresh';
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    document.getElementById('updateCurrentVer').textContent = 'v' + data.current_version;
                    document.getElementById('updateLatestVer').textContent  = 'v' + data.latest_version;
                    document.getElementById('updateLatestVer').style.color  = data.has_update ? '#16a34a' : '#1e293b';
                    document.getElementById('updateVersionInfo').style.display = 'block';
                    if (data.has_update) {
                        document.getElementById('updateStatusMsg').innerHTML = '<span style="color:#16a34a;font-weight:600;"><i class="fa fa-arrow-circle-up"></i> New version available!</span>';
                        document.getElementById('btnRunUpdate').style.display = 'inline-flex';
                    } else {
                        document.getElementById('updateStatusMsg').innerHTML = '<span style="color:#22c55e;"><i class="fa fa-check-circle"></i> You are on the latest version.</span>';
                    }
                } else {
                    document.getElementById('updateStatusMsg').innerHTML = '<span style="color:#dc2626;"><i class="fa fa-times-circle"></i> ' + (data.message || 'Check failed') + '</span>';
                }
            } catch(e) {
                document.getElementById('updateStatusMsg').innerHTML = '<span style="color:#dc2626;"><i class="fa fa-times-circle"></i> Failed to parse response</span>';
            }
        }
    };
    xhr.send('action=check_update');
}

function mikromonRunUpdate() {
    document.getElementById('btnRunUpdate').style.display = 'none';
    document.getElementById('updateStatusMsg').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Installing update...';
    var log = document.getElementById('updateProgressLog');
    log.style.display = 'block';
    log.innerHTML = '';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.details) {
                    data.details.forEach(function(d) {
                        log.innerHTML += '<div style="font-size:12px;color:#86efac;margin-bottom:4px;">' + d + '</div>';
                    });
                    log.scrollTop = log.scrollHeight;
                }
                if (data.success) {
                    document.getElementById('updateStatusMsg').innerHTML = '<span style="color:#22c55e;font-weight:600;"><i class="fa fa-check-circle"></i> ' + data.message + '</span>';
                    setTimeout(function() { window.location.reload(); }, 2000);
                } else {
                    document.getElementById('updateStatusMsg').innerHTML = '<span style="color:#dc2626;"><i class="fa fa-times-circle"></i> ' + data.message + '</span>';
                }
            } catch(e) {
                document.getElementById('updateStatusMsg').innerHTML = '<span style="color:#dc2626;"><i class="fa fa-times-circle"></i> Failed to parse response</span>';
            }
        }
    };
    xhr.send('action=run_update');
}

function closeUpdateModal() {
    document.getElementById('mikromonUpdateModal').style.display = 'none';
}

function mikromonConfirmUninstall() {
    document.getElementById('mikromonUninstallModal').style.display = 'flex';
    document.getElementById('uninstallConfirmSection').style.display = 'block';
    document.getElementById('uninstallProgressSection').style.display = 'none';
    document.getElementById('btnUninstallCancel').style.display = 'inline-flex';
    document.getElementById('btnUninstallConfirm').style.display = 'inline-flex';
    document.getElementById('btnUninstallConfirm').disabled = true;
    document.getElementById('btnUninstallConfirm').style.opacity = '0.4';
    document.getElementById('btnUninstallConfirm').style.cursor = 'not-allowed';
    document.getElementById('uninstallConfirmInput').value = '';
}

function onUninstallInputChange(input) {
    var btn = document.getElementById('btnUninstallConfirm');
    if (input.value.trim() === 'UNINSTALL') {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        input.style.borderColor = '#dc2626';
    } else {
        btn.disabled = true;
        btn.style.opacity = '0.4';
        btn.style.cursor = 'not-allowed';
        input.style.borderColor = '#e2e8f0';
    }
}

function mikromonRunUninstall() {
    document.getElementById('btnUninstallConfirm').style.display = 'none';
    document.getElementById('btnUninstallCancel').style.display = 'none';
    document.getElementById('uninstallConfirmSection').style.display = 'none';
    document.getElementById('uninstallProgressSection').style.display = 'block';
    var log = document.getElementById('uninstallProgressLog');
    log.innerHTML = '<div style="font-size:12px;color:#f59e0b;"><i class="fa fa-spinner fa-spin"></i> Starting uninstall...</div>';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            try {
                var data = JSON.parse(xhr.responseText);
                log.innerHTML = '';
                if (data.details) {
                    var details = data.details;
                    var i = 0;
                    function showNextLog() {
                        if (i < details.length) {
                            log.innerHTML += '<div style="font-size:12px;color:#86efac;margin-bottom:4px;"><i class="fa fa-check"></i> ' + details[i] + '</div>';
                            log.scrollTop = log.scrollHeight;
                            i++;
                            setTimeout(showNextLog, 400);
                        } else {
                            if (data.success) {
                                log.innerHTML += '<div style="font-size:13px;color:#22c55e;font-weight:600;margin-top:8px;"><i class="fa fa-check-circle"></i> Uninstall complete. Redirecting...</div>';
                                setTimeout(function() { window.location.href = window.location.origin + '/'; }, 2000);
                            } else {
                                log.innerHTML += '<div style="font-size:13px;color:#f87171;margin-top:8px;"><i class="fa fa-times-circle"></i> ' + data.message + '</div>';
                            }
                        }
                    }
                    showNextLog();
                }
            } catch(e) {
                log.innerHTML = '<div style="color:#f87171;">Failed to parse response</div>';
            }
        }
    };
    xhr.send('action=uninstall');
}

function closeUninstallModal() {
    var modal = document.getElementById('mikromonUninstallModal');
    if (modal) modal.style.display = 'none';
}

if (document.getElementById('mikromonUpdateModal')) {
    document.getElementById('mikromonUpdateModal').addEventListener('click', function(e) {
        if (e.target === this) closeUpdateModal();
    });
}
if (document.getElementById('mikromonUninstallModal')) {
    document.getElementById('mikromonUninstallModal').addEventListener('click', function(e) {
        if (e.target === this) closeUninstallModal();
    });
}
</script>
{/literal}

{include file="sections/footer.tpl"}
