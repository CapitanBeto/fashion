-- Fashion Intelligence — Database Setup
-- Ejecutar con: psql -U postgres -f setup_db.sql

CREATE DATABASE fashion_intelligence;
CREATE USER fi_user WITH PASSWORD 'fi_password_2024';
GRANT ALL PRIVILEGES ON DATABASE fashion_intelligence TO fi_user;
\c fashion_intelligence
GRANT ALL ON SCHEMA public TO fi_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO fi_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO fi_user;

-- pgvector para embeddings de imágenes (opcional, puede fallar si no está instalada)
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
