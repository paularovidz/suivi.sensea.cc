#!/usr/bin/env node

import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Dossiers source et destination
const SOURCE_DIR = path.join(__dirname, '../src/assets/images/icons/svg');
const DEST_DIR = path.join(__dirname, '../public/images/icons/svg');

// Fonction principale
async function main() {
  try {
    console.log('🚀 Début de la copie des SVG...');
    
    // Vérifier que le dossier source existe
    if (!await fs.access(SOURCE_DIR).then(() => true).catch(() => false)) {
      console.error(`❌ Dossier source introuvable: ${SOURCE_DIR}`);
      return;
    }
    
    // Créer le dossier de destination
    await fs.mkdir(DEST_DIR, { recursive: true });
    
    // Récupérer tous les SVG
    const svgFiles = [];
    async function scanDirectory(dir) {
      const entries = await fs.readdir(dir, { withFileTypes: true });
      for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
          await scanDirectory(fullPath);
        } else if (entry.isFile() && entry.name.endsWith('.svg')) {
          svgFiles.push(fullPath);
        }
      }
    }
    
    await scanDirectory(SOURCE_DIR);
    console.log(`📁 Trouvé ${svgFiles.length} SVG à copier`);
    
    // Copier chaque SVG
    for (const svgPath of svgFiles) {
      const relativePath = path.relative(SOURCE_DIR, svgPath);
      const destPath = path.join(DEST_DIR, relativePath);
      
      // Créer le dossier de destination si nécessaire
      const destDir = path.dirname(destPath);
      await fs.mkdir(destDir, { recursive: true });
      
      // Copier le fichier
      await fs.copyFile(svgPath, destPath);
      console.log(`✅ Copié: ${relativePath}`);
    }
    
    console.log('\n🎉 Copie des SVG terminée !');
    console.log(`📂 SVG copiés dans: ${DEST_DIR}`);
    
  } catch (error) {
    console.error('❌ Erreur:', error);
    process.exit(1);
  }
}

// Lancer le script
main();
