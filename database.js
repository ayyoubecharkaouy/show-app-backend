const { Pool } = require('pg');

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false
});

// Fonction pour tester la connexion
async function connect() {
  try {
    await pool.query('SELECT NOW()');
    console.log('Database connected successfully');
    return pool;
  } catch (err) {
    console.error('Database connection error:', err);
    throw err;
  }
}

// Création de la table si elle n'existe pas
async function initDB() {
  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS shows (
        id SERIAL PRIMARY KEY,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        category TEXT CHECK(category IN ('movie', 'anime', 'serie')) NOT NULL,
        image TEXT,
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
      )
    `);
    console.log('Database tables initialized');
  } catch (err) {
    console.error('Database initialization error:', err);
    throw err;
  }
}

// Exécute l'initialisation au démarrage
initDB();

module.exports = { pool, connect };