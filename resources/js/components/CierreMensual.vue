<template>
<section class="cm-page">
  <div class="cm-hero"><h2>Inventario mensual</h2><p>Genere y conserve el cierre mensual del almacén con saldo anterior, ingresos, egresos y saldo del mes.</p></div>
  <div class="cm-card">
    <div class="cm-title">1. Preparar cierre mensual</div>
    <div class="cm-controls"><div><label>Periodo</label><input type="month" v-model="periodo" class="form-control"></div><div><label>Almacén</label><input v-model="almacen" class="form-control"></div><div class="cm-control-action"><button class="btn-csc-orange" :disabled="cargando" @click="previsualizar">{{ cargando?'Calculando...':'Previsualizar cierre' }}</button></div></div>
    <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
  </div>
  <div v-if="preview" class="cm-card">
    <div class="cm-title">2. Resultado preliminar</div>
    <p class="cm-note">Se analizaron {{ preview.total_items }} items. La vista previa todavía no queda guardada ni congela el mes.</p>
    <div class="cm-stats"><div><span>Saldo anterior</span><strong>Bs {{ money(preview.totales.saldo_anterior_importe) }}</strong></div><div><span>Transferencias</span><strong>Bs {{ money(preview.totales.transferencia_importe) }}</strong></div><div><span>Compras locales</span><strong>Bs {{ money(preview.totales.compra_local_importe) }}</strong></div><div><span>Egresos</span><strong>Bs {{ money(preview.totales.egreso_importe) }}</strong></div><div><span>Saldo del mes</span><strong>Bs {{ money(preview.totales.saldo_mes_importe) }}</strong></div></div>
    <div class="cm-summary"><table><thead><tr><th>Grupo</th><th>Saldo anterior</th><th>Transferencias</th><th>Compras locales</th><th>Egresos</th><th>Saldo mes</th></tr></thead><tbody><tr v-for="r in preview.resumen_grupos" :key="r.grupo"><td>{{r.grupo}}</td><td>{{money(r.saldo_anterior_importe)}}</td><td>{{money(r.transferencia_importe)}}</td><td>{{money(r.compra_local_importe)}}</td><td>{{money(r.egreso_importe)}}</td><td><strong>{{money(r.saldo_mes_importe)}}</strong></td></tr></tbody></table></div>

    <div class="cm-all-items">
      <div class="cm-subtitle">Movimiento mensual físico valorado</div>
      <p class="cm-note">La tabla muestra los {{ preview.total_items }} ítems del catálogo para el período seleccionado, en una sola vista. Puede desplazarse horizontal y verticalmente como en la planilla institucional.</p>
      <div class="cm-table-tools">
        <input v-model="filtroTabla" class="form-control" placeholder="Filtrar dentro de la tabla por partida, código, descripción o grupo...">
        <select v-model="grupoSeleccionado" class="form-select cm-group-filter">
          <option value="TODOS">Todos los grupos</option>
          <option v-for="(grupo, index) in gruposPrincipales" :key="grupo" :value="grupo">{{ index + 1 }}. {{ grupo }}</option>
        </select>
        <label class="cm-stock-filter">
          <input v-model="soloConStock" type="checkbox">
          <span>Solo con stock</span>
        </label>
        <div class="cm-order-switch">
          <button type="button" :class="{ active: modoOrden === 'institucional' }" @click="modoOrden = 'institucional'">Grupo / A-Z</button>
          <button type="button" :class="{ active: modoOrden === 'codigo' }" @click="modoOrden = 'codigo'">Código LINAME</button>
        </div>
        <span>{{ detallesFiltrados.length }} de {{ preview.total_items }} ítems</span>
      </div>
      <div class="cm-full-table">
        <table>
          <colgroup>
            <col class="col-partida">
            <col class="col-codigo">
            <col class="col-descripcion">
            <col class="col-forma">
            <col v-for="n in 17" :key="'num-col-' + n" class="col-num">
          </colgroup>
          <thead>
            <tr>
              <th rowspan="2">PARTIDA PPTA</th>
              <th rowspan="2">CÓDIGO LINAME</th>
              <th rowspan="2">DESCRIPCIÓN Y CONCENTRACIÓN</th>
              <th rowspan="2">FORMA FARMACÉUTICA</th>
              <th colspan="3">SALDO ANTERIOR<br><small>AL {{ fechaCorta(preview.fecha_desde, -1) }}</small></th>
              <th colspan="3">INGRESOS<br><small>TRANSFERENCIAS ENTRE REGIONALES</small></th>
              <th colspan="3">INGRESOS<br><small>COMPRAS LOCALES</small></th>
              <th colspan="3">TOTAL INGRESOS<br><small>REGIONAL</small></th>
              <th colspan="2">EGRESOS<br><small>REGIONAL</small></th>
              <th colspan="3">SALDO DEL MES<br><small>AL {{ fechaCorta(preview.fecha_hasta, 0) }}</small></th>
            </tr>
            <tr>
              <th>CANT.</th><th>P.U.</th><th>IMPORTE</th>
              <th>CANT.</th><th>P.U.</th><th>IMPORTE</th>
              <th>CANT.</th><th>P.U.</th><th>IMPORTE</th>
              <th>CANT.</th><th>P.U.</th><th>IMPORTE</th>
              <th>CANT.</th><th>IMPORTE</th>
              <th>CANT.</th><th>P.U.</th><th>IMPORTE</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="fila in filasJerarquicas" :key="fila.key">
              <tr v-if="fila.tipo === 'grupo'" class="cm-group-row">
                <td colspan="21">{{ fila.nombre }}</td>
              </tr>
              <tr v-else-if="fila.tipo === 'subgrupo'" class="cm-subgroup-row">
                <td colspan="21">{{ fila.nombre }}</td>
              </tr>
              <tr v-else :class="{ 'cm-alt-row': fila.zebra }">
                <td>{{ fila.item.partida_codigo || '—' }}</td>
                <td>{{ fila.item.codigo || '—' }}</td>
                <td>{{ fila.item.descripcion }}</td>
                <td>{{ fila.item.forma_farmaceutica || '—' }}</td>
                <td>{{ qty(fila.item.saldo_anterior_cantidad) }}</td><td>{{ money(fila.item.saldo_anterior_precio) }}</td><td>{{ money(fila.item.saldo_anterior_importe) }}</td>
                <td>{{ qty(fila.item.transferencia_cantidad) }}</td><td>{{ money(fila.item.transferencia_precio) }}</td><td>{{ money(fila.item.transferencia_importe) }}</td>
                <td>{{ qty(fila.item.compra_local_cantidad) }}</td><td>{{ money(fila.item.compra_local_precio) }}</td><td>{{ money(fila.item.compra_local_importe) }}</td>
                <td>{{ qty(fila.item.total_ingresos_cantidad) }}</td><td>{{ money(fila.item.total_ingresos_precio) }}</td><td>{{ money(fila.item.total_ingresos_importe) }}</td>
                <td>{{ qty(fila.item.egreso_cantidad) }}</td><td>{{ money(fila.item.egreso_importe) }}</td>
                <td><strong>{{ qty(fila.item.saldo_mes_cantidad) }}</strong></td><td><strong>{{ money(fila.item.saldo_mes_precio) }}</strong></td><td><strong>{{ money(fila.item.saldo_mes_importe) }}</strong></td>
              </tr>
            </template>
            <tr v-if="!detallesFiltrados.length"><td colspan="21" class="cm-empty">No hay ítems que coincidan con el filtro.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="cm-validation">
      <div class="cm-subtitle">Validar producto antes del cierre</div>
      <p class="cm-note">Busque un producto para revisar cómo se formó su saldo: saldo anterior, ingresos, egresos y saldo calculado para el mes.</p>
      <div class="cm-search-wrap">
        <input v-model="buscarProducto" class="form-control" autocomplete="off" placeholder="Código LINAME o nombre del producto...">
        <div v-if="sugerencias.length && buscarProducto" class="cm-suggestions">
          <button v-for="p in sugerencias" :key="p.id" type="button" @click="seleccionarProducto(p)">
            <strong>{{ p.codigo }}</strong> · {{ nombreProducto(p) }}
            <small>{{ p.forma_farmaceutica || p.grupo_producto }}</small>
          </button>
        </div>
      </div>
      <div v-if="cargandoProducto" class="cm-loading">Analizando movimientos del producto...</div>
      <div v-if="errorProducto" class="alert alert-danger mt-2 mb-0">{{ errorProducto }}</div>

      <div v-if="productoDetalle" class="cm-product-review">
        <div class="cm-product-heading">
          <div>
            <strong>{{ productoDetalle.producto.codigo }} · {{ productoDetalle.producto.nombre }}</strong>
            <span>{{ productoDetalle.producto.forma_farmaceutica || 'Sin forma farmacéutica' }} · {{ productoDetalle.producto.grupo_producto || 'Sin grupo' }}</span>
          </div>
          <button type="button" class="cm-clear" @click="limpiarProducto">Limpiar</button>
        </div>

        <div class="cm-product-stats">
          <div><span>Saldo anterior</span><strong>{{ productoDetalle.calculo.saldo_anterior_cantidad }}</strong><small>Bs {{ money(productoDetalle.calculo.saldo_anterior_importe) }}</small></div>
          <div><span>Transferencias</span><strong>{{ productoDetalle.calculo.transferencia_cantidad }}</strong><small>Bs {{ money(productoDetalle.calculo.transferencia_importe) }}</small></div>
          <div><span>Compras locales</span><strong>{{ productoDetalle.calculo.compra_local_cantidad }}</strong><small>Bs {{ money(productoDetalle.calculo.compra_local_importe) }}</small></div>
          <div><span>Egresos</span><strong>{{ productoDetalle.calculo.egreso_cantidad }}</strong><small>Bs {{ money(productoDetalle.calculo.egreso_importe) }}</small></div>
          <div><span>Saldo calculado</span><strong>{{ productoDetalle.calculo.saldo_mes_cantidad }}</strong><small>Bs {{ money(productoDetalle.calculo.saldo_mes_importe) }}</small></div>
        </div>

        <div class="cm-audit-grid">
          <div>
            <h4>Ingresos que intervienen en el mes</h4>
            <div class="cm-mini-table">
              <table v-if="productoDetalle.ingresos.length"><thead><tr><th>Fecha</th><th>Tipo</th><th>Nota</th><th>Lote</th><th>Cant.</th><th>P. unit.</th><th>Importe</th></tr></thead><tbody><tr v-for="(m,i) in productoDetalle.ingresos" :key="i"><td>{{m.fecha}}</td><td>{{m.tipo}}</td><td>{{m.numero_nota || '—'}}<small v-if="m.numero_remision">Rem. {{m.numero_remision}}</small></td><td>{{m.lote}}</td><td>{{m.cantidad}}</td><td>{{money(m.precio_unitario)}}</td><td>{{money(m.importe)}}</td></tr></tbody></table>
              <div v-else class="cm-empty">No hubo ingresos de este producto durante el mes seleccionado.</div>
            </div>
          </div>
          <div>
            <h4>Egresos que intervienen en el mes</h4>
            <div class="cm-mini-table">
              <table v-if="productoDetalle.egresos.length"><thead><tr><th>Fecha</th><th>Salida</th><th>Pedido</th><th>Destino</th><th>Lote</th><th>Cant.</th><th>Importe</th></tr></thead><tbody><tr v-for="(m,i) in productoDetalle.egresos" :key="i"><td>{{m.fecha}}</td><td>{{m.numero_salida || '—'}}</td><td>{{m.numero_pedido || '—'}}</td><td>{{m.destino || '—'}}</td><td>{{m.lote}}</td><td>{{m.cantidad}}</td><td>{{money(m.importe)}}</td></tr></tbody></table>
              <div v-else class="cm-empty">No hubo egresos de este producto durante el mes seleccionado.</div>
            </div>
          </div>
        </div>

        <div class="cm-stock-compare">
          <strong>Comparación informativa con el stock actual:</strong>
          {{ productoDetalle.stock_actual.cantidad }} unidades · Bs {{ money(productoDetalle.stock_actual.importe_estimado) }}
          <small>{{ productoDetalle.nota }}</small>
        </div>
      </div>
    </div>

    <div class="cm-final"><textarea v-model="observacion" class="form-control" placeholder="Observación opcional del cierre"></textarea><button class="btn-csc-orange" :disabled="guardando" @click="cerrarMes">{{ guardando?'Cerrando...':'Confirmar y cerrar mes' }}</button></div>
  </div>
  <div class="cm-card">
    <div class="cm-title">3. Cierres mensuales registrados</div>
    <div v-if="!cierres.length" class="cm-empty">Todavía no existen cierres mensuales registrados.</div>
    <div v-for="c in cierres" :key="c.id" class="cm-row">
    <div><strong>{{ etiquetaMes(c.periodo) }}</strong><span>{{c.almacen}} · {{c.total_items}} items · Cerrado: {{c.cerrado_en || '—'}}</span></div>
    <div>
      <button class="cm-blue" @click="ver(c)">Ver</button>
      <button class="btn-csc-orange" @click="download(`/api/cierres-mensuales/${c.id}/pdf`,{stock_only:1})">PDF solo con stock</button>
      <button class="cm-blue" @click="download(`/api/cierres-mensuales/${c.id}/excel`)">EXCEL completo</button>
    </div>
  </div>
  </div>
  <div v-if="detalle" class="cm-card cm-closed-detail">
  <div class="cm-title">Detalle del cierre: {{ etiquetaMes(detalle.cierre.periodo) }}</div>
  <p class="cm-note">Cierre histórico de {{ detalle.detalles.length }} ítems. Los datos quedan congelados para trazabilidad y se muestran con la misma estructura del movimiento mensual físico valorado.</p>

  <div class="cm-table-tools cm-closed-tools">
    <input v-model="detalleFiltroTabla" class="form-control" placeholder="Filtrar dentro del cierre por partida, código, descripción o grupo...">
    <select v-model="detalleGrupoSeleccionado" class="form-select cm-group-filter">
      <option value="TODOS">Todos los grupos</option>
      <option v-for="(grupo, index) in gruposPrincipales" :key="'cerrado-'+grupo" :value="grupo">{{ index + 1 }}. {{ grupo }}</option>
    </select>
    <label class="cm-stock-filter">
      <input v-model="detalleSoloConStock" type="checkbox">
      <span>Solo con stock</span>
    </label>
    <div class="cm-order-switch">
      <button type="button" :class="{ active: detalleModoOrden === 'institucional' }" @click="detalleModoOrden = 'institucional'">Grupo / A-Z</button>
      <button type="button" :class="{ active: detalleModoOrden === 'codigo' }" @click="detalleModoOrden = 'codigo'">Código LINAME</button>
    </div>
    <span>{{ detallesCierreFiltrados.length }} de {{ detalle.detalles.length }} ítems</span>
  </div>

  <p class="cm-note cm-export-note">El PDF está pensado para una vista filtrada de hasta 1000 ítems. Para el inventario completo de miles de productos, utilice EXCEL completo.</p>
  <div class="cm-export-tools">
    <button class="btn-csc-orange" :disabled="exportando" @click="exportarCierrePdf">
      {{ exportando ? 'Generando...' : 'PDF de la vista filtrada' }}
    </button>
    <button class="cm-blue" :disabled="exportando" @click="exportarCierrePdfStock">
      PDF solo con stock
    </button>
    <button class="cm-blue" :disabled="exportando" @click="exportarCierreExcelFiltrado">
      EXCEL de la vista
    </button>
    <button class="cm-blue" :disabled="exportando" @click="exportarCierreExcelCompleto">
      EXCEL completo ({{ detalle.detalles.length }})
    </button>
  </div>

  <div class="cm-full-table">
    <table>
      <colgroup><col class="col-partida"><col class="col-codigo"><col class="col-descripcion"><col class="col-forma"><col v-for="n in 17" :key="'closed-num-'+n" class="col-num"></colgroup>
      <thead>
        <tr>
          <th rowspan="2">PARTIDA PPTA</th><th rowspan="2">CÓDIGO LINAME</th><th rowspan="2">DESCRIPCIÓN Y CONCENTRACIÓN</th><th rowspan="2">FORMA FARMACÉUTICA</th>
          <th colspan="3">SALDO ANTERIOR<br><small>AL {{ fechaCorta(detalle.cierre.fecha_desde,-1) }}</small></th>
          <th colspan="3">INGRESOS<br><small>TRANSFERENCIAS ENTRE REGIONALES</small></th>
          <th colspan="3">INGRESOS<br><small>COMPRAS LOCALES</small></th>
          <th colspan="3">TOTAL INGRESOS<br><small>REGIONAL</small></th>
          <th colspan="2">EGRESOS<br><small>REGIONAL</small></th>
          <th colspan="3">SALDO DEL MES<br><small>AL {{ fechaCorta(detalle.cierre.fecha_hasta) }}</small></th>
        </tr>
        <tr><th>CANT.</th><th>P.U.</th><th>IMPORTE</th><th>CANT.</th><th>P.U.</th><th>IMPORTE</th><th>CANT.</th><th>P.U.</th><th>IMPORTE</th><th>CANT.</th><th>P.U.</th><th>IMPORTE</th><th>CANT.</th><th>IMPORTE</th><th>CANT.</th><th>P.U.</th><th>IMPORTE</th></tr>
      </thead>
      <tbody>
        <template v-for="fila in filasDetalleJerarquicas" :key="fila.key">
          <tr v-if="fila.tipo==='grupo'" class="cm-group-row"><td colspan="21">{{fila.nombre}}</td></tr>
          <tr v-else-if="fila.tipo==='subgrupo'" class="cm-subgroup-row"><td colspan="21">{{fila.nombre}}</td></tr>
          <tr v-else :class="{ 'cm-alt-row': fila.zebra }">
            <td>{{fila.item.partida_codigo||'—'}}</td><td>{{fila.item.codigo||'—'}}</td><td>{{fila.item.descripcion}}</td><td>{{fila.item.forma_farmaceutica||'—'}}</td>
            <td>{{qty(fila.item.saldo_anterior_cantidad)}}</td><td>{{money(fila.item.saldo_anterior_precio)}}</td><td>{{money(fila.item.saldo_anterior_importe)}}</td>
            <td>{{qty(fila.item.transferencia_cantidad)}}</td><td>{{money(fila.item.transferencia_precio)}}</td><td>{{money(fila.item.transferencia_importe)}}</td>
            <td>{{qty(fila.item.compra_local_cantidad)}}</td><td>{{money(fila.item.compra_local_precio)}}</td><td>{{money(fila.item.compra_local_importe)}}</td>
            <td>{{qty(fila.item.total_ingresos_cantidad)}}</td><td>{{money(fila.item.total_ingresos_precio)}}</td><td>{{money(fila.item.total_ingresos_importe)}}</td>
            <td>{{qty(fila.item.egreso_cantidad)}}</td><td>{{money(fila.item.egreso_importe)}}</td>
            <td><strong>{{qty(fila.item.saldo_mes_cantidad)}}</strong></td><td><strong>{{money(fila.item.saldo_mes_precio)}}</strong></td><td><strong>{{money(fila.item.saldo_mes_importe)}}</strong></td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</div>
</section>
</template>
<script setup>
import {ref,onMounted,watch,computed} from 'vue'; import axios from 'axios';
import { GRUPOS_INVENTARIO, SUBGRUPOS_LABORATORIO, CATALOGO_GRUPOS_POR_CODIGO } from '../data/catalogoGrupos';
const periodo=ref(new Date().toISOString().slice(0,7)),almacen=ref('REGIONAL LA PAZ'),preview=ref(null),cierres=ref([]),detalle=ref(null),observacion=ref(''),cargando=ref(false),guardando=ref(false),error=ref('');
const buscarProducto=ref(''),sugerencias=ref([]),productoDetalle=ref(null),cargandoProducto=ref(false),errorProducto=ref('');
const filtroTabla=ref('');
const grupoSeleccionado=ref('TODOS');
const soloConStock=ref(false);
const modoOrden=ref('institucional');

const gruposPrincipales=GRUPOS_INVENTARIO;
const subgruposLaboratorio=SUBGRUPOS_LABORATORIO;

const normalizarTexto=v=>String(v||'')
  .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
  .replace(/\s+/g,' ').trim().toUpperCase();

const normalizarCodigo=v=>String(v||'').trim().toUpperCase().replace(/\.0$/,'');

const clasificacionDe=d=>{
  const codigo=normalizarCodigo(d.codigo);
  const desdeCatalogo=CATALOGO_GRUPOS_POR_CODIGO[codigo];
  if(desdeCatalogo) return desdeCatalogo;

  const grupoOrigen=normalizarTexto(d.grupo_producto);
  const grupoEncontrado=gruposPrincipales.find(g=>normalizarTexto(g)===grupoOrigen);
  const subgrupoEncontrado=subgruposLaboratorio.find(g=>normalizarTexto(g)===grupoOrigen);

  if(subgrupoEncontrado){
    return {grupo:'MATERIAL DE LABORATORIO Y REACTIVOS',subgrupo:subgrupoEncontrado};
  }
  return {grupo:grupoEncontrado||'OTROS MATERIALES Y SUMINISTROS',subgrupo:null};
};

const descripcionOrden=d=>String(d.descripcion||d.nombre||'')
  .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
  .trim().toLocaleUpperCase('es');

const detallesFiltrados=computed(()=>{
  let items=[...(preview.value?.detalles||[])];
  const q=(filtroTabla.value||'').trim().toLowerCase();

  if(q){
    items=items.filter(d=>{
      const c=clasificacionDe(d);
      return [d.partida_codigo,d.codigo,d.descripcion,d.forma_farmaceutica,d.grupo_producto,c.grupo,c.subgrupo]
        .some(v=>String(v||'').toLowerCase().includes(q));
    });
  }

  if(grupoSeleccionado.value!=='TODOS'){
    items=items.filter(d=>clasificacionDe(d).grupo===grupoSeleccionado.value);
  }

  if(soloConStock.value){
    items=items.filter(d=>Number(d.saldo_mes_cantidad||0)>0);
  }

  return items;
});

const filasJerarquicas=computed(()=>{
  const items=detallesFiltrados.value;
  const filas=[];

  let zebraIndex=0;
  const compararItems=(a,b)=>{
    if(modoOrden.value==='codigo'){
      return normalizarCodigo(a.codigo).localeCompare(
        normalizarCodigo(b.codigo),'es',{numeric:true,sensitivity:'base'}
      );
    }
    return descripcionOrden(a).localeCompare(
      descripcionOrden(b),'es',{numeric:true,sensitivity:'base'}
    );
  };

  for(const grupo of gruposPrincipales){
    const delGrupo=items.filter(d=>clasificacionDe(d).grupo===grupo);
    if(!delGrupo.length) continue;

    filas.push({tipo:'grupo',nombre:`${gruposPrincipales.indexOf(grupo)+1}. ${grupo}`,key:`g-${grupo}`});

    if(grupo==='MATERIAL DE LABORATORIO Y REACTIVOS'){
      for(const subgrupo of subgruposLaboratorio){
        const delSubgrupo=delGrupo
          .filter(d=>clasificacionDe(d).subgrupo===subgrupo)
          .sort(compararItems);

        if(!delSubgrupo.length) continue;
        filas.push({
          tipo:'subgrupo',
          nombre:`10.${subgruposLaboratorio.indexOf(subgrupo)+1}. ${subgrupo}`,
          key:`s-${subgrupo}`
        });
        delSubgrupo.forEach((item,index)=>{
          filas.push({tipo:'item',item,key:`i-${item.medicamento_id}-${subgrupo}-${index}`,zebra:zebraIndex%2===1});
          zebraIndex++;
        });
      }

      const sinSubgrupo=delGrupo
        .filter(d=>!clasificacionDe(d).subgrupo)
        .sort(compararItems);

      sinSubgrupo.forEach((item,index)=>{
        filas.push({tipo:'item',item,key:`i-${item.medicamento_id}-sin-${index}`,zebra:zebraIndex%2===1});
        zebraIndex++;
      });
    }else{
      delGrupo
        .sort(compararItems)
        .forEach((item,index)=>{
          filas.push({tipo:'item',item,key:`i-${item.medicamento_id}-${grupo}-${index}`,zebra:zebraIndex%2===1});
          zebraIndex++;
        });
    }
  }
  return filas;
});
const detalleFiltroTabla=ref('');
const detalleGrupoSeleccionado=ref('TODOS');
const detalleSoloConStock=ref(false);
const detalleModoOrden=ref('institucional');
const exportando=ref(false);

const detallesCierreFiltrados=computed(()=>{
  let items=[...(detalle.value?.detalles||[])];
  const q=normalizarTexto(detalleFiltroTabla.value||'');

  if(q){
    items=items.filter(d=>{
      const c=clasificacionDe(d);
      return [
        d.partida_codigo,d.codigo,d.descripcion,d.forma_farmaceutica,
        d.grupo_producto,c.grupo,c.subgrupo
      ].some(v=>normalizarTexto(v).includes(q));
    });
  }

  if(detalleGrupoSeleccionado.value!=='TODOS'){
    items=items.filter(d=>clasificacionDe(d).grupo===detalleGrupoSeleccionado.value);
  }

  if(detalleSoloConStock.value){
    items=items.filter(d=>Number(d.saldo_mes_cantidad||0)>0);
  }

  return items;
});

const filasDetalleJerarquicas=computed(()=>{
  const items=detallesCierreFiltrados.value;
  const filas=[]; let zebraIndex=0;

  const comparar=(a,b)=>{
    if(detalleModoOrden.value==='codigo'){
      return normalizarCodigo(a.codigo).localeCompare(
        normalizarCodigo(b.codigo),'es',{numeric:true,sensitivity:'base'}
      );
    }
    return descripcionOrden(a).localeCompare(
      descripcionOrden(b),'es',{numeric:true,sensitivity:'base'}
    );
  };

  for(const grupo of gruposPrincipales){
    const delGrupo=items.filter(d=>clasificacionDe(d).grupo===grupo);
    if(!delGrupo.length) continue;

    filas.push({
      tipo:'grupo',
      nombre:`${gruposPrincipales.indexOf(grupo)+1}. ${grupo}`,
      key:`dg-${grupo}`
    });

    if(grupo==='MATERIAL DE LABORATORIO Y REACTIVOS'){
      for(const subgrupo of subgruposLaboratorio){
        const lista=delGrupo
          .filter(d=>clasificacionDe(d).subgrupo===subgrupo)
          .sort(comparar);

        if(!lista.length) continue;

        filas.push({
          tipo:'subgrupo',
          nombre:`10.${subgruposLaboratorio.indexOf(subgrupo)+1}. ${subgrupo}`,
          key:`ds-${subgrupo}`
        });

        lista.forEach((item,i)=>{
          filas.push({
            tipo:'item',
            item,
            key:`di-${item.id||item.medicamento_id}-${subgrupo}-${i}`,
            zebra:zebraIndex%2===1
          });
          zebraIndex++;
        });
      }

      delGrupo
        .filter(d=>!clasificacionDe(d).subgrupo)
        .sort(comparar)
        .forEach((item,i)=>{
          filas.push({
            tipo:'item',
            item,
            key:`di-${item.id||item.medicamento_id}-sin-${i}`,
            zebra:zebraIndex%2===1
          });
          zebraIndex++;
        });
    }else{
      delGrupo.sort(comparar).forEach((item,i)=>{
        filas.push({
          tipo:'item',
          item,
          key:`di-${item.id||item.medicamento_id}-${grupo}-${i}`,
          zebra:zebraIndex%2===1
        });
        zebraIndex++;
      });
    }
  }

  // Los registros sin clasificación no desaparecen.
  const visibles=new Set(
    filas.filter(f=>f.tipo==='item').map(f=>f.item.id||f.item.medicamento_id)
  );
  const restantes=items
    .filter(d=>!visibles.has(d.id||d.medicamento_id))
    .sort(comparar);

  if(restantes.length){
    filas.push({
      tipo:'grupo',
      nombre:'OTROS MATERIALES Y SUMINISTROS / SIN CLASIFICACIÓN',
      key:'dg-restantes'
    });

    restantes.forEach((item,i)=>{
      filas.push({
        tipo:'item',
        item,
        key:`di-rest-${item.id||item.medicamento_id}-${i}`,
        zebra:zebraIndex%2===1
      });
      zebraIndex++;
    });
  }

  return filas;
});

function idsDetalleActual(){
  return filasDetalleJerarquicas.value
    .filter(f=>f.tipo==='item')
    .map(f=>f.item.id)
    .filter(Boolean);
}

async function exportarCierrePdf(){
  if(!detalle.value) return;
  const ids=idsDetalleActual();
  if(!ids.length){
    error.value='No hay ítems que coincidan con los filtros seleccionados.';
    return;
  }
  if(ids.length > 1000){
    error.value=`La vista actual contiene ${ids.length} ítems. Para PDF filtre hasta 1000 ítems; para el inventario completo use EXCEL completo.`;
    return;
  }
  await download(`/api/cierres-mensuales/${detalle.value.cierre.id}/pdf`,{ids});
}

async function exportarCierrePdfStock(){
  if(!detalle.value) return;
  const ids=(detalle.value.detalles||[])
    .filter(d=>Number(d.saldo_mes_cantidad||0)>0)
    .map(d=>d.id)
    .filter(Boolean);

  if(!ids.length){
    error.value='No hay productos con stock en este cierre.';
    return;
  }

  await download(`/api/cierres-mensuales/${detalle.value.cierre.id}/pdf`,{ids});
}

async function exportarCierreExcelFiltrado(){

  if(!detalle.value) return;

  const ids=idsDetalleActual();

  if(!ids.length){

    error.value='No hay ítems que coincidan con los filtros seleccionados.';

    return;

  }

  if(ids.length > 1000){

    error.value=`La vista actual contiene ${ids.length} ítems. Para un EXCEL corto, filtre hasta 1000 ítems; para el inventario completo use EXCEL COMPLETO.`;

    return;

  }

  await download(`\/api\/cierres-mensuales\/${detalle.value.cierre.id}\/excel`,{ids});

}

async function exportarCierreExcelCompleto(){
  if(!detalle.value) return;
  await download(`/api/cierres-mensuales/${detalle.value.cierre.id}/excel`);
}

function nombreProducto(p){
  const nombre=String(p?.nombre||'').trim();
  const concentracion=String(p?.concentracion||'').trim();
  if(!concentracion) return nombre;
  const n=normalizarTexto(nombre), c=normalizarTexto(concentracion);
  return n.includes(c)?nombre:`${nombre} ${concentracion}`.trim();
}
let buscarTimer=null, ignorarBusqueda=false;
const money=v=>Number(v||0).toLocaleString('es-BO',{minimumFractionDigits:2,maximumFractionDigits:2});
const qty=v=>Number(v||0).toLocaleString('es-BO',{maximumFractionDigits:3});
function fechaSegura(valor){
  if(!valor) return null;
  const texto=String(valor).trim().replace(' ','T');
  const d=new Date(texto.includes('T') ? texto : `${texto}T12:00:00`);
  return Number.isNaN(d.getTime()) ? null : d;
}
function fechaCorta(fecha, ajuste=0){
  const d=fechaSegura(fecha);
  if(!d) return '—';
  d.setDate(d.getDate()+Number(ajuste||0));
  return d.toLocaleDateString('es-BO',{day:'2-digit',month:'2-digit',year:'numeric'});
}
const etiquetaMes=v=>{
  const d=fechaSegura(v);
  return d ? d.toLocaleDateString('es-BO',{month:'long',year:'numeric'}).toUpperCase() : 'PERIODO SIN FECHA VÁLIDA';
};
watch(buscarProducto, (valor) => {
  if(ignorarBusqueda){ignorarBusqueda=false;return;}
  clearTimeout(buscarTimer);
  const q=(valor||'').trim();
  if(q.length<1){sugerencias.value=[];return;}
  buscarTimer=setTimeout(async()=>{
    try{
      const {data}=await axios.get('/api/medicamentos',{params:{buscar:q}});
      sugerencias.value=data;
    }catch{ sugerencias.value=[]; }
  },220);
});
async function seleccionarProducto(p){
  ignorarBusqueda=true;
  buscarProducto.value=`${p.codigo} · ${nombreProducto(p)}`;
  sugerencias.value=[];
  productoDetalle.value=null;errorProducto.value='';cargandoProducto.value=true;
  try{
    const {data}=await axios.get(`/api/cierres-mensuales/productos/${p.id}/preview`,{params:{periodo:periodo.value,almacen:almacen.value}});
    productoDetalle.value=data;
  }catch(e){errorProducto.value=e.response?.data?.message||'No se pudo analizar el producto.';}
  finally{cargandoProducto.value=false;}
}
function limpiarProducto(){buscarProducto.value='';sugerencias.value=[];productoDetalle.value=null;errorProducto.value='';}
watch([periodo,almacen],()=>{if(productoDetalle.value) limpiarProducto();});

async function cargar(){const {data}=await axios.get('/api/cierres-mensuales');cierres.value=data;}
async function previsualizar(){error.value='';preview.value=null;filtroTabla.value='';grupoSeleccionado.value='TODOS';soloConStock.value=false;modoOrden.value='institucional';cargando.value=true;try{const {data}=await axios.get('/api/cierres-mensuales/preview',{params:{periodo:periodo.value,almacen:almacen.value}});preview.value=data;}catch(e){error.value=e.response?.data?.message||'No se pudo calcular el cierre.';}finally{cargando.value=false;}}
async function cerrarMes(){if(!confirm(`¿Confirmar el cierre de ${etiquetaMes(periodo.value+'-01')}? El resultado quedará guardado como histórico.`))return;guardando.value=true;error.value='';try{await axios.post('/api/cierres-mensuales',{periodo:periodo.value,almacen:almacen.value,observacion:observacion.value});preview.value=null;observacion.value='';await cargar();alert('Cierre mensual registrado correctamente.');}catch(e){error.value=e.response?.data?.message||'No se pudo registrar el cierre.';}finally{guardando.value=false;}}
async function ver(c){
  error.value='';
  detalleFiltroTabla.value='';
  detalleGrupoSeleccionado.value='TODOS';
  detalleSoloConStock.value=false;
  detalleModoOrden.value='institucional';
  const {data}=await axios.get(`/api/cierres-mensuales/${c.id}`);
  detalle.value=data;
}

async function download(url,params={}){
  exportando.value=true;
  error.value='';
  try{
    const r=await axios.get(url,{responseType:'blob',timeout:360000,params});
    const cd=r.headers['content-disposition']||'';
    const m=cd.match(/filename="?([^";]+)"?/);
    const a=document.createElement('a');
    a.href=URL.createObjectURL(r.data);
    a.download=m?.[1]||'reporte';
    document.body.appendChild(a);a.click();a.remove();
    setTimeout(()=>URL.revokeObjectURL(a.href),1000);
  }catch(e){
    const data=e.response?.data;
    if(data instanceof Blob){
      try{
        const text=await data.text();
        const json=JSON.parse(text);
        error.value=json.message||'No se pudo generar el archivo.';
      }catch{
        error.value='No se pudo generar el archivo.';
      }
    }else{
      error.value=e.response?.data?.message||'No se pudo generar el archivo.';
    }
  }finally{
    exportando.value=false;
  }
}
onMounted(cargar);
</script>
<style scoped>
.cm-page{width:100%;max-width:none;margin:16px 0}.cm-hero{background:#0b3d62;color:#fff;border-radius:14px 14px 0 0;padding:20px}.cm-hero h2{margin:0;font-size:1.3rem;font-weight:700}.cm-hero p{margin:5px 0 0;color:#dce7ef}.cm-card{background:#fff;border:1px solid #dbe3ea;border-radius:12px;margin-top:16px;padding:18px;box-shadow:0 4px 15px rgba(11,61,98,.06)}.cm-title{font-weight:700;font-size:1.1rem;color:#0b3d62;border-bottom:2px solid #e85d04;padding-bottom:9px;margin-bottom:14px}.cm-controls{display:grid;grid-template-columns:1fr 1.5fr auto;gap:14px;align-items:end}.cm-controls label{font-weight:600;color:#243447;margin-bottom:5px;display:block}.cm-control-action{padding-top:27px}.cm-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}.cm-stats>div{border-left:3px solid #0b3d62;background:#f4f7fa;padding:11px;border-radius:7px}.cm-stats span{display:block;color:#667788;font-size:.78rem}.cm-stats strong{color:#0b3d62;font-size:1rem}.cm-summary,.cm-detail-table{overflow:auto;max-height:500px;margin-top:15px}.cm-summary table,.cm-detail-table table{width:100%;border-collapse:collapse;font-size:.88rem}.cm-summary th,.cm-detail-table th{background:#0b3d62;color:#fff}.cm-summary th,.cm-summary td,.cm-detail-table th,.cm-detail-table td{padding:8px;border:1px solid #dbe3ea;text-align:left}.cm-final{display:flex;gap:12px;align-items:center;margin-top:14px}.cm-final textarea{min-height:44px}.cm-row{display:flex;justify-content:space-between;gap:15px;align-items:center;padding:11px 0;border-bottom:1px solid #e5ebf0}.cm-row span{display:block;color:#667788;font-size:.83rem}.cm-blue{background:#fff;color:#0b3d62;border:1px solid #0b3d62;border-radius:7px;padding:7px 12px;margin-left:6px}.cm-row .btn-csc-orange{margin-left:6px}.cm-empty,.cm-note{color:#667788}.btn-csc-orange{border-radius:7px;padding:8px 14px}.btn-csc-orange:disabled{opacity:.65}@media(max-width:900px){.cm-controls,.cm-stats{grid-template-columns:1fr}.cm-control-action{padding-top:0}.cm-final,.cm-row{flex-direction:column;align-items:stretch}}

.cm-all-items{margin-top:18px;padding-top:4px;border-top:1px solid #e3e9ee}
.cm-table-tools{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0}.cm-export-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 0 14px}.cm-export-tools button{margin:0}.cm-closed-tools{margin-top:8px}.cm-closed-tools>input{min-width:320px;flex:1 1 320px}
.cm-table-tools input{width:min(540px,100%)}
.cm-table-tools span{color:#667788;font-size:.85rem;white-space:nowrap}
.cm-group-filter{width:auto;min-width:245px}
.cm-stock-filter{display:flex;align-items:center;gap:7px;margin:0;padding:8px 10px;background:#f1f5f8;border:1px solid #d6e0e8;border-radius:7px;color:#30485c;font-size:.84rem;font-weight:600;white-space:nowrap}
.cm-stock-filter input{width:auto;margin:0}
.cm-order-switch{display:flex;border:1px solid #b8c9d6;border-radius:7px;overflow:hidden;background:#fff}
.cm-order-switch button{border:0;background:#fff;color:#486073;padding:8px 11px;font-size:.8rem;font-weight:700;white-space:nowrap}
.cm-order-switch button+button{border-left:1px solid #cbd7df}
.cm-order-switch button.active{background:#0b3d62;color:#fff}
.cm-full-table{width:100%;overflow:auto;max-height:720px;border:1px solid #dbe3ea;background:#fff}
.cm-full-table table{width:100%;min-width:0;border-collapse:separate;border-spacing:0;table-layout:fixed;font-size:.73rem}
.cm-full-table col.col-partida{width:74px}.cm-full-table col.col-codigo{width:78px}.cm-full-table col.col-descripcion{width:250px}.cm-full-table col.col-forma{width:115px}.cm-full-table col.col-num{width:58px}
.cm-full-table th{background:#0b3d62;color:#fff;text-align:center;white-space:normal;position:sticky;z-index:4;line-height:1.15}
.cm-full-table thead tr:first-child th{top:0;height:50px;z-index:6}
.cm-full-table thead tr:nth-child(2) th{top:50px;z-index:7}
.cm-full-table thead th[rowspan]{top:0!important;z-index:8}
.cm-full-table th small{font-weight:400;color:#dce7ef;font-size:.65rem}
.cm-full-table th,.cm-full-table td{padding:6px 5px;border-right:1px solid #dbe3ea;border-bottom:1px solid #dbe3ea;vertical-align:middle}
.cm-full-table thead tr:first-child th{border-top:0}.cm-full-table th:first-child,.cm-full-table td:first-child{border-left:0}
.cm-full-table td{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#fff;text-align:center}
.cm-full-table td:nth-child(3),.cm-full-table td:nth-child(4){white-space:normal;overflow:visible;text-overflow:clip;line-height:1.25}
.cm-full-table tbody tr.cm-alt-row td{background:#dfe9f1}
.cm-full-table tbody tr:hover:not(.cm-group-row):not(.cm-subgroup-row) td{background:#cbdce8!important}
.cm-full-table td:nth-child(n+5){text-align:center}
.cm-full-table .cm-empty{text-align:center;padding:18px}
.cm-full-table .cm-group-row td{background:#0b3d62;color:#fff;font-weight:800;font-size:.82rem;letter-spacing:.02em;padding:8px 10px;text-align:left}
.cm-full-table .cm-subgroup-row td{background:#cfdce6;color:#0b3d62;font-weight:800;padding:7px 14px;border-left:5px solid #e85d04;text-align:left}
.cm-validation{margin-top:18px;padding-top:4px;border-top:1px solid #e3e9ee}.cm-subtitle{font-weight:700;color:#0b3d62;font-size:1rem;margin-top:14px}.cm-search-wrap{position:relative;max-width:720px}.cm-suggestions{position:absolute;z-index:20;left:0;right:0;top:calc(100% + 4px);background:#fff;border:1px solid #cfdbe5;border-radius:8px;box-shadow:0 10px 22px rgba(11,61,98,.14);max-height:260px;overflow:auto}.cm-suggestions button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:9px 12px;color:#243447}.cm-suggestions button:hover{background:#f1f5f8}.cm-suggestions small{display:block;color:#687889;margin-top:2px}.cm-loading{color:#0b3d62;margin-top:9px;font-size:.9rem}.cm-product-review{margin-top:16px;background:#f7f9fb;border:1px solid #dbe4ec;border-radius:10px;padding:14px}.cm-product-heading{display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:2px solid #e85d04;padding-bottom:9px}.cm-product-heading strong{color:#0b3d62;font-size:1rem}.cm-product-heading span{display:block;color:#667788;font-size:.85rem;margin-top:2px}.cm-clear{background:#fff;border:1px solid #0b3d62;color:#0b3d62;border-radius:6px;padding:5px 10px}.cm-product-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:9px;margin-top:12px}.cm-product-stats>div{background:#fff;border-left:3px solid #0b3d62;border-radius:6px;padding:9px}.cm-product-stats span,.cm-product-stats small{display:block;color:#687889;font-size:.76rem}.cm-product-stats strong{display:block;color:#0b3d62;font-size:1.05rem}.cm-audit-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px}.cm-audit-grid h4{font-size:.95rem;color:#0b3d62;margin:0 0 7px}.cm-mini-table{overflow:auto;max-height:300px;background:#fff;border:1px solid #dbe3ea}.cm-mini-table table{width:100%;border-collapse:collapse;font-size:.78rem}.cm-mini-table th{background:#0b3d62;color:#fff}.cm-mini-table th,.cm-mini-table td{padding:6px;border:1px solid #dbe3ea;text-align:left;vertical-align:top}.cm-mini-table small{display:block;color:#687889}.cm-stock-compare{margin-top:14px;padding:10px;background:#fff;border-left:3px solid #e85d04;color:#33495c}.cm-stock-compare small{display:block;color:#687889;margin-top:3px}.cm-stock-compare strong{color:#0b3d62}@media(max-width:900px){.cm-product-stats,.cm-audit-grid{grid-template-columns:1fr}}

</style>
