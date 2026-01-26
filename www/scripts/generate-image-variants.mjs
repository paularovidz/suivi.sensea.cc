#!/usr/bin/env node

import sharp from 'sharp';
import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configuration des tailles responsives
const SIZES = [640, 750, 828, 1080, 1200, 1920, 2048];
const FORMATS = ['webp']; // AVIF temporairement désactivé (erreur 500)

       // Dossiers source et destination
       const SOURCE_DIR = path.join(__dirname, '../src/assets/images');
       const DEST_DIR = path.join(__dirname, '../public/images');
       
       // Dossier SVG source et destination
       const SVG_SOURCE_DIR = path.join(__dirname, '../src/assets/images/icons/svg');
       const SVG_DEST_DIR = path.join(__dirname, '../public/images/icons/svg');

       // Fonction pour générer une variante d'image
       async function generateImageVariant(inputPath, outputPath, width, format, forceRegenerate = false, quality = 80) {
         try {
           // Vérifier si l'image existe déjà et est plus récente que la source
           if (!forceRegenerate) {
             try {
               const inputStats = await fs.stat(inputPath);
               const outputStats = await fs.stat(outputPath);
               
               // Si l'image de sortie est plus récente que la source, on skip
               if (outputStats.mtime > inputStats.mtime) {
                 console.log(`⏭️  Skippé (déjà à jour): ${path.basename(outputPath)}`);
                 return;
               }
             } catch (error) {
               // L'image de sortie n'existe pas, on continue
             }
           }
           
           const image = sharp(inputPath);
           
           // Redimensionner
           if (width) {
             image.resize(width, null, { withoutEnlargement: true });
           }
           
           // Convertir au format demandé avec des paramètres optimisés
           switch (format) {
             case 'webp':
               await image.webp({ 
                 quality,
                 effort: 6, // Plus d'effort = meilleure compression
                 nearLossless: true // Compression quasi-sans perte
               }).toFile(outputPath);
               break;
             case 'avif':
               await image.avif({ 
                 quality,
                 effort: 9, // Effort maximum pour AVIF
                 chromaSubsampling: '4:2:0', // Meilleure compression
                 speed: 6 // Vitesse de compression (0-10, 6 = bon compromis)
               }).toFile(outputPath);
               break;
             default:
               await image.toFile(outputPath);
           }
           
           // Vérifier la taille du fichier généré
           const stats = await fs.stat(outputPath);
           const sizeKB = (stats.size / 1024).toFixed(1);
           
           console.log(`✅ Généré: ${path.basename(outputPath)} (${sizeKB} KB)`);
         } catch (error) {
           console.error(`❌ Erreur pour ${outputPath}:`, error.message);
         }
       }

       // Fonction pour copier les SVG
       async function copySvgFiles() {
         try {
           // Vérifier que le dossier source SVG existe
           if (!await fs.access(SVG_SOURCE_DIR).then(() => true).catch(() => false)) {
             console.log('⚠️  Dossier SVG source introuvable, skip...');
             return;
           }
           
           // Créer le dossier de destination SVG
           await fs.mkdir(SVG_DEST_DIR, { recursive: true });
           
           // Récupérer tous les SVG
           const svgFiles = [];
           async function scanSvgDirectory(dir) {
             const entries = await fs.readdir(dir, { withFileTypes: true });
             for (const entry of entries) {
               const fullPath = path.join(dir, entry.name);
               if (entry.isDirectory()) {
                 await scanSvgDirectory(fullPath);
               } else if (entry.isFile() && entry.name.endsWith('.svg')) {
                 svgFiles.push(fullPath);
               }
             }
           }
           
           await scanSvgDirectory(SVG_SOURCE_DIR);
           console.log(`📁 Trouvé ${svgFiles.length} SVG à copier`);
           
           // Copier chaque SVG
           for (const svgPath of svgFiles) {
             const relativePath = path.relative(SVG_SOURCE_DIR, svgPath);
             const destPath = path.join(SVG_DEST_DIR, relativePath);
             
             // Créer le dossier de destination si nécessaire
             const destDir = path.dirname(destPath);
             await fs.mkdir(destDir, { recursive: true });
             
             // Copier le fichier
             await fs.copyFile(svgPath, destPath);
             console.log(`✅ SVG copié: ${relativePath}`);
           }
         } catch (error) {
           console.error('❌ Erreur lors de la copie des SVG:', error);
         }
       }
       
       // Fonction pour traiter une image
       async function processImage(imagePath, forceRegenerate = false) {
  const relativePath = path.relative(SOURCE_DIR, imagePath);
  const dirName = path.dirname(relativePath);
  const baseName = path.basename(imagePath, path.extname(imagePath));
  const ext = path.extname(imagePath);
  
  // Créer le dossier de destination
  const destSubDir = path.join(DEST_DIR, dirName);
  await fs.mkdir(destSubDir, { recursive: true });
  
  // Copier l'image originale
  const originalDest = path.join(destSubDir, `${baseName}${ext}`);
  await fs.copyFile(imagePath, originalDest);
  console.log(`✅ Copié: ${path.basename(originalDest)}`);
  
           // Générer les variantes WebP et AVIF
         for (const format of FORMATS) {
           const formatDest = path.join(destSubDir, `${baseName}.${format}`);
           await generateImageVariant(imagePath, formatDest, null, format, forceRegenerate);
           
           // Générer les tailles responsives pour ce format
           for (const size of SIZES) {
             const sizeDest = path.join(destSubDir, `${baseName}-${size}w.${format}`);
             await generateImageVariant(imagePath, sizeDest, size, format, forceRegenerate);
           }
         }
}

       // Fonction principale
       async function main() {
         try {
           // Vérifier si on force la régénération
           const forceRegenerate = process.argv.includes('--force');
           if (forceRegenerate) {
             console.log('🔄 Mode forcé : régénération de toutes les images...');
           } else {
             console.log('🚀 Début de la génération des variantes d\'images...');
             console.log('💡 Utilisez --force pour forcer la régénération');
           }
    
    // Vérifier que le dossier source existe
    if (!await fs.access(SOURCE_DIR).then(() => true).catch(() => false)) {
      console.error(`❌ Dossier source introuvable: ${SOURCE_DIR}`);
      return;
    }
    
    // Créer le dossier de destination
    await fs.mkdir(DEST_DIR, { recursive: true });
    
    // Récupérer toutes les images
    const images = [];
    async function scanDirectory(dir) {
      const entries = await fs.readdir(dir, { withFileTypes: true });
      for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
          await scanDirectory(fullPath);
        } else if (entry.isFile() && /\.(jpg|jpeg|png|gif)$/i.test(entry.name)) {
          images.push(fullPath);
        }
      }
    }
    
    await scanDirectory(SOURCE_DIR);
    console.log(`📁 Trouvé ${images.length} images à traiter`);
    
                          // Traiter chaque image
           for (const imagePath of images) {
             console.log(`\n🔄 Traitement de: ${path.basename(imagePath)}`);
             await processImage(imagePath, forceRegenerate);
           }
           
           // Copier les SVG
           console.log('\n🔄 Copie des SVG...');
           await copySvgFiles();
           
           console.log('\n🎉 Génération terminée !');
           console.log(`📂 Images générées dans: ${DEST_DIR}`);
           console.log(`📂 SVG copiés dans: ${SVG_DEST_DIR}`);
    
  } catch (error) {
    console.error('❌ Erreur:', error);
    process.exit(1);
  }
}

// Lancer le script
main();
