#!/usr/bin/env node

import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Dossiers à scanner
const SRC_DIR = path.join(__dirname, '../src');

// Fonction pour remplacer OptimizedImage par CleanImage
async function migrateFile(filePath) {
  try {
    const content = await fs.readFile(filePath, 'utf-8');
    
    // Remplacer les imports
    let newContent = content.replace(
      /import\s+OptimizedImage\s+from\s+["']@\/components\/utilities\/OptimizedImage\.astro["']/g,
      'import CleanImage from "@/components/utilities/CleanImage.astro"'
    );
    
    // Remplacer les utilisations du composant
    newContent = newContent.replace(
      /<OptimizedImage/g,
      '<CleanImage'
    );
    
    // Garder les props width et height (cruciales pour la performance)
    // CleanImage les supporte maintenant
    
    // Si le contenu a changé, écrire le fichier
    if (newContent !== content) {
      await fs.writeFile(filePath, newContent, 'utf-8');
      console.log(`✅ Migré: ${path.relative(SRC_DIR, filePath)}`);
      return true;
    }
    
    return false;
  } catch (error) {
    console.error(`❌ Erreur lors de la migration de ${filePath}:`, error.message);
    return false;
  }
}

// Fonction pour scanner récursivement les dossiers
async function scanDirectory(dir) {
  const entries = await fs.readdir(dir, { withFileTypes: true });
  let migratedCount = 0;
  
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    
    if (entry.isDirectory()) {
      // Ignorer node_modules et .git
      if (entry.name !== 'node_modules' && entry.name !== '.git') {
        migratedCount += await scanDirectory(fullPath);
      }
    } else if (entry.isFile() && entry.name.endsWith('.astro')) {
      const migrated = await migrateFile(fullPath);
      if (migrated) migratedCount++;
    }
  }
  
  return migratedCount;
}

// Fonction principale
async function main() {
  try {
    console.log('🚀 Début de la migration vers CleanImage...');
    
    const migratedCount = await scanDirectory(SRC_DIR);
    
    console.log(`\n🎉 Migration terminée !`);
    console.log(`📁 ${migratedCount} fichiers migrés`);
    console.log('\n📋 Prochaines étapes :');
    console.log('1. Lancer "npm run generate-images" pour créer les variantes');
    console.log('2. Tester votre site');
    console.log('3. Vérifier que toutes les images s\'affichent correctement');
    
  } catch (error) {
    console.error('❌ Erreur:', error);
    process.exit(1);
  }
}

// Lancer le script
main();
