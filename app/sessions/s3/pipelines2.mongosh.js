// Session 3, part 1: arrays, joins and buckets in the pipeline
// Run in:  docker compose exec mongodb mongosh catalogue

// $unwind: one document per array element. Predict the count before and after.
db.villagers.aggregate([ { $count: "villagers" } ])
db.villagers.aggregate([ { $unwind: "$furniture" }, { $count: "ownerships" } ])
db.villagers.aggregate([ { $match: { name: "Admiral" } }, { $unwind: "$furniture" },
                         { $project: { _id: 0, name: 1, furniture: 1 } }, { $limit: 3 } ])

// the most owned furniture: unwind, then group by the element
db.villagers.aggregate([
  { $unwind: "$furniture" },
  { $group: { _id: "$furniture", owners: { $sum: 1 } } },
  { $sort: { owners: -1, _id: 1 } },
  { $limit: 5 }
])

// $lookup: the join, as a stage. The matches arrive as an array.
db.villagers.aggregate([
  { $match: { name: "Admiral" } },
  { $lookup: { from: "items", localField: "furniture", foreignField: "_id", as: "owned" } },
  { $project: { _id: 0, name: 1, n: { $size: "$owned" }, first: { $first: "$owned.name" } } }
])

// the most owned furniture, with names: unwind, group, lookup, unwind again, shape
db.villagers.aggregate([
  { $unwind: "$furniture" },
  { $group: { _id: "$furniture", owners: { $sum: 1 } } },
  { $sort: { owners: -1, _id: 1 } },
  { $limit: 5 },
  { $lookup: { from: "items", localField: "_id", foreignField: "_id", as: "item" } },
  { $unwind: "$item" },
  { $project: { _id: 0, item: "$item.name", sell: "$item.sell", owners: 1 } }
])

// the join the other way: which villagers own item 717, per item document
db.items.aggregate([
  { $match: { _id: { $in: [717, 1849] } } },
  { $lookup: { from: "villagers", localField: "_id", foreignField: "furniture", as: "owners" } },
  { $project: { _id: 0, name: 1, owners: "$owners.name" } }
])

// group by an expression: villagers per birthday month, with the month as key
db.villagers.aggregate([
  { $group: { _id: "$birthday.month", n: { $sum: 1 } } },
  { $sort: { n: -1 } },
  { $limit: 3 }
])

// $bucket: price bands, with a count and three example names per band
db.items.aggregate([
  { $match: { sell: { $exists: true } } },
  { $bucket: { groupBy: "$sell", boundaries: [0, 1000, 10000, 100000, 2000000], default: "other",
               output: { n: { $sum: 1 }, examples: { $firstN: { n: 3, input: "$name" } } } } }
])

// $facet: several pipelines over the same input, one result document
db.items.aggregate([
  { $match: { type: "fish" } },
  { $facet: {
      byHabitat: [ { $sortByCount: "$whereHow" }, { $limit: 3 } ],
      priciest:  [ { $sort: { sell: -1 } }, { $limit: 3 }, { $project: { _id: 0, name: 1, sell: 1 } } ],
      total:     [ { $count: "fish" } ]
  } }
])

// $merge: write the report into a collection, and keep it fresh with the next run
db.villagers.aggregate([
  { $unwind: "$furniture" },
  { $group: { _id: "$furniture", owners: { $sum: 1 } } },
  { $merge: { into: "itemPopularity", whenMatched: "replace", whenNotMatched: "insert" } }
])
db.itemPopularity.find().sort({ owners: -1 }).limit(3)
db.itemPopularity.countDocuments()
db.itemPopularity.drop()
