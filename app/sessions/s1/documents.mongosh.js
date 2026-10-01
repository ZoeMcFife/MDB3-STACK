// Session 1 starter: the documents to paste into mongosh, one block at a time.
// Open the shell first:  docker compose exec mongodb mongosh sandbox
// The queries between the blocks are typed in the session; they are short on purpose.

// Step 1: the 3D glasses
db.items.insertOne({
  name: "3D glasses", type: "accessory", buy: 490, sell: 122,
  variations: ["White", "Black"], colors: ["White", "Colorful"],
  source: "Able Sisters", style: "Active"
})

// Step 2: two more shapes
db.items.insertOne({
  name: "angelfish", type: "fish", sell: 3000, where: "River", shadow: "Small",
  available: { north: { May: "4 PM - 9 AM", Jun: "4 PM - 9 AM",
                        Jul: "4 PM - 9 AM", Aug: "4 PM - 9 AM",
                        Sep: "4 PM - 9 AM", Oct: "4 PM - 9 AM" } }
})
db.items.insertOne({
  name: "Agent K.K.", type: "music", buy: 3200, sell: 800, source: "K.K. concert"
})

// Step 5: Admiral and Boone
db.villagers.insertOne({
  name: "Admiral", species: "Bird", personality: "Cranky", hobby: "Nature",
  birthday: { month: 1, day: 27 }, catchphrase: "aye aye",
  favoriteSong: "Steep Hill", styles: ["Cool"], colors: ["Black", "Blue"],
  home: { wallpaper: "dirt-clod wall", flooring: "tatami" },
  furniture: [717, 1849, 7047, 2736, 787, 5970, 3449, 3622, 3802, 4106, 3438, 4029]
})
db.villagers.insertOne({
  name: "Boone", species: "Gorilla", personality: "Jock", hobby: "Fitness",
  birthday: { month: 9, day: 12 }, catchphrase: "baboom",
  favoriteSong: "K.K. Rally", styles: ["Active"], colors: ["Blue", "Black"],
  home: { wallpaper: "kitchen wall", flooring: "brown wood-block flooring" },
  furniture: [717, 4029, 3449]
})

// Step 10: the broken pochette (type it exactly like this, capital N and the quotes included)
db.items.insertOne({ Name: "acorn pochette", type: "bag", sell: "2400" })
