INSTRUCCIONES

1. Copia tus archivos dentro de esta carpeta:
   data/dim_pokemon.csv
   data/fact_encounters.csv

2. Abre index.html.

3. Si la carga automática no funciona por restricciones del navegador, usa los botones de la barra izquierda para cargar manualmente los dos CSV.

Qué se agregó para que parezca proyecto BI:
- Seguridad RLS simulada por roles.
- Drill-through al hacer clic en Capturas por zona o Top Pokémon.
- Medidas calculadas tipo DAX en JavaScript: % captura, CP promedio, crecimiento mensual.
- Filtros OLAP por entrenador, zona, capturado, año, mes, Pokémon y nivel.
- Modelo estrella documentado en el dashboard.
- Matriz tipo OLAP con entrenador vs zona.
