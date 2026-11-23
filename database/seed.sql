-- Carga los estados de reparación y la versión del esquema, sin cuentas ni operaciones.
SET NAMES utf8mb4;
INSERT INTO estados_reparacion (codigo, nombre, orden) VALUES
('recibido', 'Recibido', 1),
('enviado_tecnico', 'Enviado a técnico', 2),
('diagnostico', 'Diagnóstico', 3),
('en_reparacion', 'En reparación', 4),
('listo_entrega', 'Listo para entrega', 5),
('entregado', 'Entregado', 6),
('cancelado', 'Cancelado', 7),
('no_reparable', 'No reparable', 8);
-- Marcador final: la comprobación exige que la inicialización haya terminado.
INSERT INTO schema_version (version) VALUES (1);
