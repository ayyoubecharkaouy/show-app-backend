const express = require('express');
const router = express.Router();
const multer = require('multer');
const { pool } = require('../database');
const { v2: cloudinary } = require('cloudinary');

// Configuration Cloudinary
cloudinary.config({
  cloud_name: process.env.CLOUDINARY_NAME,
  api_key: process.env.CLOUDINARY_KEY,
  api_secret: process.env.CLOUDINARY_SECRET
});

// Configuration Multer pour les uploads temporaires
const upload = multer({ dest: 'uploads/' });

// Validation des données
const validateShow = [
  body('title').trim().notEmpty().withMessage('Title is required'),
  body('description').trim().notEmpty().withMessage('Description is required'),
  body('category').isIn(['movie', 'anime', 'serie']).withMessage('Invalid category')
];

// GET all shows
router.get('/', async (req, res) => {
  try {
    const { rows } = await pool.query('SELECT * FROM shows ORDER BY created_at DESC');
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// POST new show
router.post('/', upload.single('image'), async (req, res) => {
  try {
    const { title, description, category } = req.body;
    
    // Upload image to Cloudinary if provided
    let imageUrl = null;
    if (req.file) {
      const result = await cloudinary.uploader.upload(req.file.path);
      imageUrl = result.secure_url;
    }

    const { rows } = await pool.query(
      'INSERT INTO shows (title, description, category, image) VALUES ($1, $2, $3, $4) RETURNING *',
      [title, description, category, imageUrl]
    );

    res.status(201).json(rows[0]);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// PUT update show
router.put('/:id', upload.single('image'), async (req, res) => {
  try {
    const { id } = req.params;
    const { title, description, category } = req.body;

    // Upload new image if provided
    let imageUrl = null;
    if (req.file) {
      const result = await cloudinary.uploader.upload(req.file.path);
      imageUrl = result.secure_url;
    }

    const { rows } = await pool.query(
      `UPDATE shows 
       SET title = $1, description = $2, category = $3, 
           image = COALESCE($4, image), updated_at = NOW() 
       WHERE id = $5 RETURNING *`,
      [title, description, category, imageUrl, id]
    );

    if (rows.length === 0) {
      return res.status(404).json({ error: 'Show not found' });
    }

    res.json(rows[0]);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// DELETE show
router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { rowCount } = await pool.query('DELETE FROM shows WHERE id = $1', [id]);

    if (rowCount === 0) {
      return res.status(404).json({ error: 'Show not found' });
    }

    res.json({ message: 'Show deleted successfully' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;