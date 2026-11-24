// Session 3, part 2: indexes and explain
// Run in:  docker compose exec mongodb mongosh catalogue

// explain: how did the server find the 452 items that sell for 10,000 or more?
db.items.find({ sell: { $gte: 10000 } }).explain("executionStats").executionStats

// an index on sell, then the same explain
db.items.createIndex({ sell: 1 })
db.items.find({ sell: { $gte: 10000 } }).explain("executionStats").executionStats

// a sort uses the index too; without it the server sorts in memory
db.items.find({ sell: { $gte: 10000 } }).sort({ sell: -1 }).explain("executionStats").executionStats.executionStages.stage
db.items.find({ type: "fish" }).sort({ sell: -1 }).explain("executionStats").executionStats.executionStages.stage

// compound index: equality first, then sort, then range (ESR)
db.items.createIndex({ type: 1, sell: -1 })
db.items.find({ type: "fish" }).sort({ sell: -1 }).explain("executionStats").executionStats

// an index on an array field indexes every element: the junction table's index
db.villagers.createIndex({ furniture: 1 })
db.villagers.find({ furniture: 717 }).explain("executionStats").executionStats
db.villagers.getIndexes()

// unique: the constraint that find() could not give you. Villagers are copied into the sandbox first.
db = db.getSiblingDB("sandbox")
db.villagers.drop()
db.villagers.insertMany(db.getSiblingDB("catalogue").villagers.find().toArray())
db.villagers.createIndex({ name: 1 }, { unique: true })
db.villagers.insertOne({ name: "Admiral", species: "Bird" })

// text index: MATCH ... AGAINST. One per collection, any number of fields.
db = db.getSiblingDB("catalogue")
db.items.createIndex({ name: "text", sourceNotes: "text" })
db.items.find({ $text: { $search: "guitar" } }, { name: 1, _id: 0 })
db.items.find({ $text: { $search: "cherry lamp" } }, { name: 1, score: { $meta: "textScore" }, _id: 0 })
  .sort({ score: { $meta: "textScore" } }).limit(3)

// every index costs on writes; drop what nobody uses. Leave the catalogue as it was.
db.items.dropIndex({ sell: 1 })
db.items.getIndexes().map(i => i.name)
db.items.dropIndexes()
db.villagers.dropIndexes()
