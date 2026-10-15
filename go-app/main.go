package main

import (
    "fmt"
    "log"
    "net/http"
)

func handler(w http.ResponseWriter, r *http.Request) {
    fmt.Fprintf(w, "Hello from Go in Docker!")
}

func main() {
    http.HandleFunc("/", handler)
    fmt.Println("Server running on port 8080")
    log.Fatal(http.ListenAndServe("0.0.0.0:8080", nil))
}
