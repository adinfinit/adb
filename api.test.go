package main

import (
	"fmt"
	"io/ioutil"
	"net/http"
	"strings"
)

const Prefix = "http://localhost"

type Entry struct {
	ID      string                 `json:"id,omitempty"`
	Version int                    `json:"version,omitempty"`
	Time    string                 `json:"time,omitempty"`
	Meta    map[string]interface{} `json:"meta,omitempty"`
	Type    string                 `json:"type,omitempty"`
	Data    map[string]interface{} `json:"data,omitempty"`
}

type Operation struct {
	Request string `json:"request"`
	Entry   Entry  `json:"entry"`
}

func StoreRequest(name, store, data string) {
	fmt.Println("=== CASE " + name)
	url := Prefix + "/store/" + store

	resp, err := http.Post(url, "application/json", strings.NewReader(data))
	if err != nil {
		fmt.Println("Failed POST:", err)
		return
	}
	defer resp.Body.Close()

	body, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		fmt.Println("Failed ReadAll:", err)
		return
	}

	fmt.Println(resp.StatusCode)
	fmt.Println(string(body))
}

func main() {
	StoreRequest("insert", "api.test", `{
		"method": "transaction",
		"data": [
			{
				"method": "delete",
				"data": {}
			},
			{
				"method": "insert",
				"data": {
					"id": "AAAAAAAA-0000-0000-0000-000000000000",
					"version": 10,
					"date": "2017-06-18T13:35:27Z",
					"meta": {},
					"type": "note",
					"data": {"title": "Alpha"}
				}
			},
			{
				"method": "insert",
				"data": {
					"id": "BBBBBBBB-0000-0000-0000-000000000000",
					"version": 20,
					"date": "2017-06-18T18:35:27Z",
					"meta": {},
					"type": "note",
					"data": {"title": "Beta"}
				}
			},
			{
				"method": "delete",
				"data": {
					"id": "BBBBBBBB-0000-0000-0000-000000000000",
					"version": 20,
					"date": "2017-06-18T18:35:27Z",
					"meta": {},
					"type": "note",
					"data": {"title": "Beta"}
				}
			}
		]
	}`)
}
