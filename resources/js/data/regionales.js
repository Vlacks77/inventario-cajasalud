export const regionalConfig = {
  Chuquisaca: { codigo: 'ch', bandera: 'chuquisaca.svg' },
  'La Paz': { codigo: 'lp', bandera: 'lapaz.svg' },
  Cochabamba: { codigo: 'cbba', bandera: 'cochabamba.svg' },
  Oruro: { codigo: 'or', bandera: 'oruro.svg' },
  Potosí: { codigo: 'pt', bandera: 'potosi.svg' },
  Tarija: { codigo: 'tj', bandera: 'tarija.svg' },
  'Santa Cruz': { codigo: 'sc', bandera: 'santacruz.svg' },
  Beni: { codigo: 'bn', bandera: 'beni.svg' },
  Pando: { codigo: 'pa', bandera: 'pando.svg' },
};

export const obtenerRegional = nombre => {
  return regionalConfig[nombre] || {
    codigo: '',
    bandera: '',
  };
};
