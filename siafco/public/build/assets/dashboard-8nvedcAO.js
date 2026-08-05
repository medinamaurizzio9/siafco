import{_ as n}from"./app-BZ_Z-tk_.js";const l=["#0b1f3a","#d4af37","#0f9f9a","#64748b"],d=t=>Array.isArray(t)&&t.some(s=>Number(s)>0),c=t=>{t.innerHTML=`
        <div class="empty-state min-h-60">
            <span class="empty-state-icon" aria-hidden="true">--</span>
            <div>
                <p class="font-black text-siafco-primary-900">Sin datos</p>
                <p class="mt-1 text-sm">El grafico se completara cuando exista informacion suficiente.</p>
            </div>
        </div>
    `};async function b(){const t=document.querySelector("[data-dashboard-charts]");if(!t)return;let s={};try{s=JSON.parse(t.dataset.dashboardCharts||"{}")}catch{return}const{default:o}=await n(async()=>{const{default:a}=await import("./apexcharts.esm-LmkCAMZ-.js");return{default:a}},[]);document.querySelectorAll("[data-chart]").forEach(a=>{const r=a.dataset.chart,e=s[r];if(!e||!d(e.series)){c(a);return}a.innerHTML="",new o(a,{chart:{type:r==="operations"?"bar":"donut",height:240,toolbar:{show:!1},animations:{enabled:!0,speed:200},fontFamily:"Instrument Sans, system-ui, sans-serif"},colors:l,labels:e.labels,series:e.series.map(i=>Number(i)),legend:{position:"bottom",fontSize:"12px",markers:{width:8,height:8,radius:8}},dataLabels:{enabled:!1},stroke:{width:0},plotOptions:{bar:{borderRadius:6,columnWidth:"45%",distributed:!0},pie:{donut:{size:"68%",labels:{show:!0,total:{show:!0,label:"Total"}}}}},xaxis:{categories:e.labels,labels:{style:{colors:"#64748b"}}},yaxis:{labels:{style:{colors:"#64748b"}}},grid:{borderColor:"#dbe3ef"},tooltip:{theme:"light"}}).render()})}export{b as initDashboardCharts};
